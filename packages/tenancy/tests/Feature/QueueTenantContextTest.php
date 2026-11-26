<?php

declare(strict_types=1);

use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\WorkQueueHandlers;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Tests\Support\Queue\RecordContextJob;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses()->group('pg');

/** Two real account databases, each with its own marker and the same workspace id. */
beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    RecordContextJob::$runs = [];
    $this->databases = [];
    $this->accounts = [];
    $this->workspaceId = (string) Str::uuid();

    foreach (['a', 'b'] as $name) {
        $database = 'corex_qtc_'.$name.'_'.bin2hex(random_bytes(5)).'_test';
        $this->databases[$name] = $database;
        DB::statement('CREATE DATABASE '.$database);
        $configuration = DB::connection('pgsql')->getConfig();
        $configuration['database'] = $database;
        $connection = new ConnectionFactory(app())->make($configuration, $database);

        try {
            $connection->statement('CREATE TABLE wsp_workspaces (id uuid PRIMARY KEY, slug text, type text, path text, parent_id uuid, deleted_at timestamptz)');
            $connection->table('wsp_workspaces')->insert(['id' => $this->workspaceId, 'slug' => 'ws-'.$name, 'type' => 'company', 'path' => 'ws-'.$name]);
        } finally {
            $connection->disconnect();
        }

        $cluster = (string) Str::uuid();
        $identity = (string) Str::uuid();
        DB::table('root_clusters')->insert(['id' => $cluster, 'name' => $name, 'pg_host_writer' => 'writer.test', 'pgbouncer_host' => 'pool.test', 'app_db_user' => 'corex']);
        DB::table('root_identities')->insert(['id' => $identity, 'email' => $identity.'@example.test']);
        $account = Account::on('pgsql')->create(['cluster_id' => $cluster, 'db_name' => $database, 'slug' => 'acc-'.$name, 'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024, 'owner_identity_id' => $identity, 'status' => 'active']);
        $this->accounts[$name] = new AccountRef(id: $account->id, slug: 'acc-'.$name, status: 'active', features: [], limits: []);
    }

    $this->host = WorkScopeFixture::install(app());
    resolve(WorkQueueHandlers::class)->tenant(RecordContextJob::class);
    $routes = $this->databases;
    $accounts = $this->accounts;
    app()->scoped(PhysicalBindingVerifier::class, static fn () => new class($routes, $accounts) implements PhysicalBindingVerifier
    {
        public function __construct(private array $databases, private array $accounts) {}

        public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
        {
            $expected = $this->accounts['a']->id === $context->account->id ? $this->databases['a'] : $this->databases['b'];
            $actual = $connection->selectOne('select current_database() as physical')->physical;

            if ($actual !== $expected || $binding->bindingId !== 'resource:'.hash('sha256', $context->account->id)) {
                throw new ScopeContextMismatch('Physical account mismatch.');
            }
        }
    });
    $this->workspace = static fn (string $name): WorkspaceRef => new WorkspaceRef(id: test()->workspaceId, slug: 'ws-'.$name, type: 'company', path: 'ws-'.$name, parentId: null);
});

afterEach(function (): void {
    endTenantContext();
    DB::purge('tenant');

    foreach ($this->databases as $database) {
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS '.$database);
    }
});

it('captures a signed scope at enqueue and resumes the original account, workspace and physical database', function (): void {
    $manager = resolve(TenancyManager::class);
    $payloads = [];
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$payloads): void {
        $payloads[] = $event->job->payload();
    });
    foreach (['a', 'b', 'a'] as $name) {
        $manager->runFor($this->accounts[$name], ($this->workspace)($name), function () use ($name): void {
            $this->host->scope();
            Queue::connection('sync')->push(new RecordContextJob($name));
        });
        expect($manager->context())->toBeNull();
    }
    expect(array_column(RecordContextJob::$runs, 'database'))->toBe([$this->databases['a'], $this->databases['b'], $this->databases['a']])
        ->and(array_column(RecordContextJob::$runs, 'workspace'))->toBe(['ws-a', 'ws-b', 'ws-a'])
        ->and($payloads[0]['corex_scope']['scope']['account_id'])->toBe($this->accounts['a']->id)
        ->and($payloads[0]['corex_scope']['scope']['version'])->toBe(3);
    $saved = $payloads[1];
    $job = new SyncJob(app(), json_encode($saved, JSON_THROW_ON_ERROR), 'sync', 'default');
    Event::dispatch(new JobProcessing('sync', $job));

    try {
        $job->fire();
    } finally {
        Event::dispatch(new JobAttempted('sync', $job));
    }
    expect(RecordContextJob::$runs[3]['database'])->toBe($this->databases['b'])->and($manager->context())->toBeNull();
    $saved['corex_scope']['scope']['principal_id'] = 'substitute';
    $job = new SyncJob(app(), json_encode($saved, JSON_THROW_ON_ERROR), 'sync', 'default');
    expect(fn () => Event::dispatch(new JobProcessing('sync', $job)))->toThrow(ScopeContextMismatch::class)
        ->and($job->isDeleted())->toBeTrue()->and(RecordContextJob::$runs)->toHaveCount(4);
});

it('restores the outer account after a failing nested synchronous job', function (): void {
    $manager = resolve(TenancyManager::class);
    $manager->runFor($this->accounts['a'], ($this->workspace)('a'), function () use ($manager): void {
        $this->host->scope();
        Queue::connection('sync')->push(new RecordContextJob('first'));

        try {
            $manager->runFor($this->accounts['b'], ($this->workspace)('b'), function (): void {
                $this->host->scope();
                Queue::connection('sync')->push(new RecordContextJob('failed', true));
            });
            test()->fail('The inner job did not fail.');
        } catch (RuntimeException $error) {
            expect($error)->not->toBeNull();
        }
        expect($manager->context()?->workspace?->slug)->toBe('ws-a');
        Queue::connection('sync')->push(new RecordContextJob('last'));
    });
    expect(array_column(RecordContextJob::$runs, 'tag'))->toBe(['first', 'failed', 'last'])
        ->and($manager->context())->toBeNull()->and(DB::getDefaultConnection())->toBe('pgsql');
});

/** Reserve a real Laravel database job stamped in the producer's account and workspace. */
function reserveScopeJob(string $name): DatabaseJob
{
    $broker = DB::connection('pgsql');
    $broker->statement('CREATE TABLE IF NOT EXISTS scope_queue_jobs (id bigserial PRIMARY KEY, queue text, payload text, attempts int, reserved_at int, available_at int, created_at int)');
    config(['queue.connections.scope_broker' => [
        'driver' => 'database', 'connection' => 'pgsql', 'queue' => 'work', 'table' => 'scope_queue_jobs', 'retry_after' => 90,
    ]]);
    $queue = Queue::connection('scope_broker');
    $fixture = test();
    resolve(TenancyManager::class)->runFor($fixture->accounts[$name], ($fixture->workspace)($name), function () use ($fixture, $queue, $name): void {
        $fixture->host->scope();
        $queue->push(new RecordContextJob($name));
    });
    $job = $queue->pop();
    expect($job)->toBeInstanceOf(DatabaseJob::class);

    return $job;
}

function fireReservedScopeJob(DatabaseJob $job): void
{
    Event::dispatch(new JobProcessing('scope_broker', $job));

    try {
        $job->fire();
    } finally {
        Event::dispatch(new JobAttempted('scope_broker', $job));
    }
}

it('quarantines a correctly signed job when its account has disappeared from the central directory', function (): void {
    $job = reserveScopeJob('a');
    DB::connection('pgsql')->table('root_accounts')->where('id', $this->accounts['a']->id)->delete();

    expect(fn () => fireReservedScopeJob($job))->toThrow(ScopeContextMismatch::class)
        ->and($job->hasFailed())->toBeTrue()
        ->and($job->isDeleted())->toBeTrue()
        ->and(RecordContextJob::$runs)->toBe([])
        ->and(resolve(TenancyManager::class)->context())->toBeNull()
        ->and(DB::getDefaultConnection())->toBe('pgsql');
});

it('refuses a retired workspace even when the previous account has a live workspace with the same id', function (): void {
    $job = reserveScopeJob('b');
    $manager = resolve(TenancyManager::class);
    $manager->runFor($this->accounts['b'], null, fn () => DB::table('wsp_workspaces')->where('id', $this->workspaceId)->delete());
    $manager->runFor($this->accounts['a'], ($this->workspace)('a'), function () use ($manager, $job): void {
        $this->host->scope();
        expect(fn () => fireReservedScopeJob($job))->toThrow(ScopeContextMismatch::class)
            ->and($job->hasFailed())->toBeTrue()
            ->and($manager->context()?->workspace?->slug)->toBe('ws-a')
            ->and(DB::selectOne('select current_database() as owner')->owner)->toBe($this->databases['a'])
            ->and(RecordContextJob::$runs)->toBe([]);
    });
});

it('rejects an earlier restore epoch at delivery and accepts a fresh enqueue for the unchanged account database', function (): void {
    $oldJob = reserveScopeJob('a');
    $owner = resolve(DatabaseScopeControl::class);
    $account = $this->accounts['a'];
    $route = $owner->resolve($account);
    $closed = $owner->drain($account->id, $owner->close($account->id, $route->controlRevision));
    $next = new PhysicalBinding($route->deploymentId, $route->bindingId, $route->bindingGeneration, $route->accountIncarnation, str_repeat('e', 64));
    $owner->reopen($account->id, $closed, $next, fn (PhysicalBinding $candidate): bool => resolve(TenancyManager::class)->runFor($account, null, fn () => DB::selectOne('select current_database() as owner')->owner === $this->databases['a']
        && $candidate->bindingId === 'resource:'.hash('sha256', $account->id)));

    expect(fn () => fireReservedScopeJob($oldJob))->toThrow(ScopeStale::class)
        ->and($oldJob->isDeleted())->toBeTrue()
        ->and(RecordContextJob::$runs)->toBe([]);
    $fresh = reserveScopeJob('a');
    fireReservedScopeJob($fresh);
    expect($fresh->payload()['corex_scope']['scope']['restore_epoch'])->toBe($next->restoreEpoch)
        ->and(array_column(RecordContextJob::$runs, 'database'))->toBe([$this->databases['a']]);
});

it('ignores a repeated completion for the inner job and preserves the admitted outer Laravel frame', function (): void {
    $outer = reserveScopeJob('a');
    $inner = reserveScopeJob('b');
    Event::dispatch(new JobProcessing('scope_broker', $outer));

    try {
        fireReservedScopeJob($inner);
        Event::dispatch(new JobAttempted('scope_broker', $inner));
        $outer->fire();
    } finally {
        Event::dispatch(new JobAttempted('scope_broker', $outer));
    }
    expect(array_column(RecordContextJob::$runs, 'database'))->toBe([$this->databases['b'], $this->databases['a']])
        ->and(array_column(RecordContextJob::$runs, 'workspace'))->toBe(['ws-b', 'ws-a'])
        ->and(resolve(TenancyManager::class)->context())->toBeNull()
        ->and(DB::getDefaultConnection())->toBe('pgsql');
});
