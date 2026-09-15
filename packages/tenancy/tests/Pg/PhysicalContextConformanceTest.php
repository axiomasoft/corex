<?php

declare(strict_types=1);

use CoreX\Contracts\TenantStorage;
use CoreX\Exceptions\CentralContextException;
use CoreX\Storage\StorageOperationFailed;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Models\Account;
use CoreX\Tenancy\Tests\TestCase;
use CoreX\Tenancy\WorkspaceRef;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(TestCase::class)->group('pg');

/** @param array<string, mixed> $payload */
function physicalContextJob(array $payload): Job
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn($payload);

    return $job;
}

beforeEach(function (): void {
    expect(app()->environment())->toBe('testing')
        ->and(str_ends_with(DB::connection('pgsql')->getDatabaseName(), '_test'))->toBeTrue();
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->artisan('migrate', ['--database' => 'pgsql', '--path' => realpath(__DIR__.'/../../database/migrations/root'), '--realpath' => true, '--force' => true])->run();
    DB::statement('CREATE TABLE context_sentinel (name text NOT NULL)');
    DB::table('context_sentinel')->insert(['name' => 'root']);
    $this->databases = [];
    $this->accounts = [];
    $this->workspaceId = (string) Str::uuid();
    $this->storageRoot = sys_get_temp_dir().'/corex-physical-context-'.bin2hex(random_bytes(12));
    config(['tenancy.storage.durable_root' => $this->storageRoot.'/durable', 'tenancy.storage.ephemeral_root' => $this->storageRoot.'/ephemeral']);

    foreach (['archive', 'lab'] as $name) {
        $database = 'corex_p3_context_'.bin2hex(random_bytes(6)).'_test';
        $this->databases[] = $database;
        DB::statement('CREATE DATABASE '.$database);
        $configuration = DB::connection('pgsql')->getConfig();
        $configuration['database'] = $database;
        $connection = (new ConnectionFactory(app()))->make($configuration, $database);

        try {
            $connection->statement('CREATE TABLE context_sentinel (name text NOT NULL)');
            $connection->table('context_sentinel')->insert(['name' => $name]);
            $connection->statement('CREATE TABLE wsp_workspaces (id uuid PRIMARY KEY, slug text, type text, path text, parent_id uuid, deleted_at timestamptz)');
            $connection->table('wsp_workspaces')->insert(['id' => $this->workspaceId, 'slug' => $name, 'type' => 'company', 'path' => $name]);
        } finally {
            $connection->disconnect();
        }
        $cluster = (string) Str::uuid();
        $identity = (string) Str::uuid();
        DB::table('root_clusters')->insert(['id' => $cluster, 'name' => $name, 'pg_host_writer' => 'writer.test', 'pgbouncer_host' => 'pool.test', 'app_db_user' => 'corex']);
        DB::table('root_identities')->insert(['id' => $identity, 'email' => $identity.'@example.test']);
        $account = Account::on('pgsql')->create(['cluster_id' => $cluster, 'db_name' => $database, 'slug' => $name, 'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024, 'owner_identity_id' => $identity, 'status' => 'active']);
        $this->accounts[$name] = new AccountRef(id: $account->id, slug: $name, status: 'active', features: [], limits: []);
    }
});

afterEach(function (): void {
    app(TenancyManager::class)->end();
    DB::purge('tenant');
    foreach ($this->databases as $database) {
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS '.$database);
    }
    File::deleteDirectory($this->storageRoot);
});

it('clears previous workspace for another job in the same account and refuses unavailable narrowing', function (): void {
    $manager = app(TenancyManager::class);
    $account = $this->accounts['archive'];
    $job = physicalContextJob(['tenant_id' => $account->id, 'workspace_id' => $this->workspaceId]);
    Event::dispatch(new JobProcessing('database', $job));
    expect($manager->context()->workspace->slug)->toBe('archive');

    Event::dispatch(new JobProcessing('database', physicalContextJob(['tenant_id' => $account->id])));
    expect($manager->context()->workspace)->toBeNull();

    $manager->setWorkspace(new WorkspaceRef(id: $this->workspaceId, slug: 'archive', type: 'company', path: 'archive', parentId: null));
    expect(fn () => Event::dispatch(new JobProcessing('database', physicalContextJob(['tenant_id' => $account->id, 'workspace_id' => (string) Str::uuid()]))))
        ->toThrow(RuntimeException::class, 'Queued workspace context is unavailable.');
    expect($manager->context()->workspace)->toBeNull();
});

it('runs archive and laboratory jobs on distinct physical databases with success exception retry and nested restoration', function (): void {
    $manager = app(TenancyManager::class);
    $archive = $this->accounts['archive'];
    $lab = $this->accounts['lab'];
    $archivePayload = ['tenant_id' => $archive->id, 'workspace_id' => $this->workspaceId];
    $archiveJob = physicalContextJob($archivePayload);
    Event::dispatch(new JobProcessing('database', $archiveJob));
    expect(DB::table('context_sentinel')->value('name'))->toBe('archive');
    $archiveDisk = app(TenantStorage::class)->disk();
    $archiveDisk->put('document.txt', 'archive');
    Event::dispatch(new JobProcessed('database', $archiveJob));
    expect($manager->context())->toBeNull()->and(DB::table('context_sentinel')->value('name'))->toBe('root');
    expect(fn () => $archiveDisk->get('document.txt'))->toThrow(CentralContextException::class);

    $rootJob = physicalContextJob([]);
    Event::dispatch(new JobProcessing('database', $rootJob));
    expect(DB::table('context_sentinel')->value('name'))->toBe('root');
    Event::dispatch(new JobProcessed('database', $rootJob));

    $labJob = physicalContextJob(['tenant_id' => $lab->id, 'workspace_id' => $this->workspaceId]);
    Event::dispatch(new JobProcessing('database', $labJob));
    expect(DB::table('context_sentinel')->value('name'))->toBe('lab')
        ->and($manager->context()->workspace->slug)->toBe('lab')
        ->and(app(TenantStorage::class)->disk()->exists('document.txt'))->toBeFalse();
    expect(fn () => $archiveDisk->get('document.txt'))->toThrow(StorageOperationFailed::class);
    Event::dispatch(new JobExceptionOccurred('database', $labJob, new RuntimeException('interrupted lab job')));
    expect($manager->context())->toBeNull()->and(DB::table('context_sentinel')->value('name'))->toBe('root');

    Event::dispatch(new JobRetryRequested((object) ['payload' => json_encode($archivePayload, JSON_THROW_ON_ERROR)]));
    $outerWorkspace = $manager->context()->workspace;
    expect(DB::table('context_sentinel')->value('name'))->toBe('archive')->and($archiveDisk->get('document.txt'))->toBe('archive');
    expect(fn () => $manager->runFor($lab, null, function (): void {
        expect(DB::table('context_sentinel')->value('name'))->toBe('lab');

        throw new RuntimeException('nested lab failure');
    }))->toThrow(RuntimeException::class, 'nested lab failure');
    expect(DB::table('context_sentinel')->value('name'))->toBe('archive')->and($manager->context()->workspace)->toBe($outerWorkspace);
    Event::dispatch(new JobProcessed('database', $archiveJob));
    expect($manager->context())->toBeNull()->and(DB::table('context_sentinel')->value('name'))->toBe('root');
});
