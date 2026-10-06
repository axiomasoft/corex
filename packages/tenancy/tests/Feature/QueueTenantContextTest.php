<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use CoreX\Tenancy\Runtime\Internal\QueueTenantContext;
use CoreX\Tenancy\Tests\Support\Queue\RecordContextJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $connection = (new ConnectionFactory(app()))->make($configuration, $database);

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

    $this->workspace = static fn (string $name): WorkspaceRef => new WorkspaceRef(id: test()->workspaceId, slug: 'ws-'.$name, type: 'company', path: 'ws-'.$name, parentId: null);
});

afterEach(function (): void {
    endTenantContext();
    DB::purge('tenant');

    foreach ($this->databases as $database) {
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS '.$database);
    }
});

/** A job object as the worker hands it to the events. */
function queuedJobQTC(array $payload): Job
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn($payload);

    return $job;
}

function tenantPayloadQTC(string $account, ?string $workspace = null): array
{
    return ['corex_tenant' => ['account' => $account, 'workspace' => $workspace]];
}

it('stamps the payload of a job pushed inside a context with that account and workspace', function (): void {
    $payloads = [];
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$payloads): void {
        $payloads[] = $event->job->payload()['corex_tenant'] ?? null;
    });

    app(TenancyManager::class)->runFor($this->accounts['a'], ($this->workspace)('a'), function (): void {
        RecordContextJob::dispatch('inside')->onConnection('sync');
    });
    RecordContextJob::dispatch('central')->onConnection('sync');

    expect($payloads)->toBe([['account' => $this->accounts['a']->id, 'workspace' => $this->workspaceId], null])
        ->and(RecordContextJob::$runs[0])->toMatchArray(['account' => $this->accounts['a']->id, 'workspace' => 'ws-a'])
        ->and(RecordContextJob::$runs[1])->toMatchArray(['account' => null]);
});

it('a worker processes job A then job B in one process: each sees its own account and database, central in between', function (): void {
    $manager = app(TenancyManager::class);
    $seen = [];

    foreach (['a', 'b'] as $name) {
        $job = queuedJobQTC(tenantPayloadQTC($this->accounts[$name]->id));
        Event::dispatch(new JobProcessing('database', $job));
        $seen[] = [$manager->context()?->account->id, DB::connection()->getDatabaseName()];
        Event::dispatch(new JobAttempted('database', $job));

        expect($manager->initialized())->toBeFalse()
            ->and(DB::getDefaultConnection())->toBe('pgsql');
    }

    expect($seen)->toBe([
        [$this->accounts['a']->id, $this->databases['a']],
        [$this->accounts['b']->id, $this->databases['b']],
    ]);
});

it('a sync job dispatched inside A/workspace runs under A/workspace and restores the caller afterwards', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $manager->setWorkspace(($this->workspace)('a'));

    RecordContextJob::dispatch('sync-ok')->onConnection('sync');

    expect(RecordContextJob::$runs)->toBe([[
        'account' => $this->accounts['a']->id, 'workspace' => 'ws-a', 'database' => $this->databases['a'], 'tag' => 'sync-ok',
    ]])
        ->and($manager->context()->account->id)->toBe($this->accounts['a']->id)
        ->and($manager->context()->workspace?->slug)->toBe('ws-a');
});

it('real nested sync jobs restore the outer account and workspace after success and after failure', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $manager->setWorkspace(($this->workspace)('a'));

    $manager->runFor($this->accounts['b'], ($this->workspace)('b'), function (): void {
        RecordContextJob::dispatch('inner-ok')->onConnection('sync');
    });
    expect(RecordContextJob::$runs[0])->toMatchArray(['account' => $this->accounts['b']->id, 'workspace' => 'ws-b', 'tag' => 'inner-ok'])
        ->and($manager->context()->account->id)->toBe($this->accounts['a']->id)
        ->and($manager->context()->workspace?->slug)->toBe('ws-a');

    $manager->runFor($this->accounts['b'], ($this->workspace)('b'), function (): void {
        expect(fn () => RecordContextJob::dispatch('inner-fail', true)->onConnection('sync'))
            ->toThrow(RuntimeException::class, 'job failed: inner-fail');
    });

    expect($manager->context()->account->id)->toBe($this->accounts['a']->id)
        ->and($manager->context()->workspace?->slug)->toBe('ws-a');
});

it('a nested sync job frame restores the outer context after success (explicit events)', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $manager->setWorkspace(($this->workspace)('a'));
    $inner = new SyncJob(app(), json_encode(tenantPayloadQTC($this->accounts['b']->id, $this->workspaceId) + ['job' => 'x', 'data' => []]), 'sync', 'default');

    Event::dispatch(new JobProcessing('sync', $inner));
    expect($manager->context()->account->id)->toBe($this->accounts['b']->id)
        ->and($manager->context()->workspace?->slug)->toBe('ws-b');
    Event::dispatch(new JobAttempted('sync', $inner));

    expect($manager->context()->account->id)->toBe($this->accounts['a']->id)
        ->and($manager->context()->workspace?->slug)->toBe('ws-a');
});

it('a nested sync job frame restores the outer context after a processing failure', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $manager->setWorkspace(($this->workspace)('a'));
    $inner = new SyncJob(app(), json_encode(tenantPayloadQTC($this->accounts['b']->id, (string) Str::uuid()) + ['job' => 'x', 'data' => []]), 'sync', 'default');

    // the workspace of the payload does not exist in B → processing fails half-way ...
    expect(fn () => Event::dispatch(new JobProcessing('sync', $inner)))->toThrow(TenantContextUnavailable::class);
    expect($manager->context()->account->id)->toBe($this->accounts['b']->id);

    // ... and the worker's `finally` still reports the attempt
    Event::dispatch(new JobAttempted('sync', $inner, new RuntimeException('failed')));

    expect($manager->context()->account->id)->toBe($this->accounts['a']->id)
        ->and($manager->context()->workspace?->slug)->toBe('ws-a');
});

it('an async job starts central, ends central, and a repeated completion cannot pop another job frame', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']); // left over from earlier code in the worker
    $job = queuedJobQTC(tenantPayloadQTC($this->accounts['b']->id));

    Event::dispatch(new JobProcessing('database', $job));
    expect($manager->context()->account->id)->toBe($this->accounts['b']->id);

    $other = queuedJobQTC(tenantPayloadQTC($this->accounts['a']->id));
    Event::dispatch(new JobAttempted('database', $other)); // not the job on top of the stack: ignored
    expect($manager->context()->account->id)->toBe($this->accounts['b']->id);

    Event::dispatch(new JobAttempted('database', $job));
    Event::dispatch(new JobAttempted('database', $job)); // repeated completion
    expect($manager->initialized())->toBeFalse();
});

it('a job whose account is missing fails without falling back to the previous database', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $job = queuedJobQTC(tenantPayloadQTC((string) Str::uuid()));

    expect(fn () => Event::dispatch(new JobProcessing('database', $job)))->toThrow(TenantContextUnavailable::class);
    expect($manager->initialized())->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('pgsql');

    Event::dispatch(new JobAttempted('database', $job, new TenantContextUnavailable('x')));
    expect(DB::getDefaultConnection())->toBe('pgsql');
});

it('a malformed payload is refused', function (mixed $payload): void {
    $job = queuedJobQTC(['corex_tenant' => $payload]);

    expect(fn () => Event::dispatch(new JobProcessing('database', $job)))->toThrow(TenantContextUnavailable::class, 'malformed');
})->with([
    'not an array' => ['a'],
    'no account' => [['workspace' => null]],
    'no workspace key' => [['account' => 'x']],
    'workspace not a string' => [['account' => 'x', 'workspace' => 5]],
]);

it('queue:retry re-runs the stored payload under its original account', function (): void {
    $manager = app(TenancyManager::class);
    $payload = tenantPayloadQTC($this->accounts['a']->id);

    foreach ([1, 2] as $attempt) {
        $job = queuedJobQTC($payload);
        Event::dispatch(new JobProcessing('database', $job));
        expect($manager->context()->account->id)->toBe($this->accounts['a']->id);
        Event::dispatch(new JobAttempted('database', $job));
        expect($manager->initialized())->toBeFalse();
    }
});

it('a central job (no payload key) runs central and a sync caller is restored only after it', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $sync = new SyncJob(app(), json_encode(['job' => 'x', 'data' => []]), 'sync', 'default');

    Event::dispatch(new JobProcessing('sync', $sync));
    expect($manager->initialized())->toBeFalse();
    Event::dispatch(new JobAttempted('sync', $sync));

    expect($manager->context()->account->id)->toBe($this->accounts['a']->id);
});

it('is a single instance keeping its frames per application, not in static state', function (): void {
    expect(app(QueueTenantContext::class))->toBe(app(QueueTenantContext::class));
});
