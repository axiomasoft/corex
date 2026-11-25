<?php

declare(strict_types=1);

use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Exceptions\StorageOperationFailed;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Tests\TestCase;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(TestCase::class)->group('pg');

beforeEach(function (): void {
    expect(app()->environment())->toBe('testing')
        ->and(str_ends_with(DB::connection('pgsql')->getDatabaseName(), '_test'))->toBeTrue();
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
    DB::statement('CREATE TABLE context_sentinel (name text NOT NULL)');
    DB::table('context_sentinel')->insert(['name' => 'root']);
    $this->databases = [];
    $this->accounts = [];
    $this->workspaceId = (string) Str::uuid();
    $this->storageRoot = sys_get_temp_dir().'/corex-physical-context-'.bin2hex(random_bytes(12));
    config(['corex-tenancy.storage.durable_root' => $this->storageRoot.'/durable', 'corex-tenancy.storage.ephemeral_root' => $this->storageRoot.'/ephemeral']);

    foreach (['archive', 'lab'] as $name) {
        $database = 'corex_p3_context_'.bin2hex(random_bytes(6)).'_test';
        $this->databases[$name] = $database;
        DB::statement('CREATE DATABASE '.$database);
        $configuration = DB::connection('pgsql')->getConfig();
        $configuration['database'] = $database;
        $connection = new ConnectionFactory(app())->make($configuration, $database);

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
    $host = WorkScopeFixture::install(app());
    $expected = [];
    foreach ($this->accounts as $name => $account) {
        $expected[$account->id] = $this->databases[$name];
    }
    app()->scoped(PhysicalBindingVerifier::class, static fn () => new class($expected) implements PhysicalBindingVerifier
    {
        public function __construct(private array $databases) {}

        public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
        {
            if ($connection->selectOne('select current_database() as physical')->physical !== $this->databases[$context->account->id]) {
                throw new ScopeContextMismatch;
            }
        }
    });
    foreach ($this->accounts as $name => $account) {
        $workspace = new WorkspaceRef($this->workspaceId, $name, 'company', $name, null);
        $this->scopes[$name] = resolve(TenancyManager::class)->runFor($account, $workspace, fn () => $host->scope());
        $this->proofs[$name] = resolve(TenancyManager::class)->runFor($account, $workspace, fn () => resolve(StoredWorkIssuer::class)->issue($this->scopes[$name], 'test.pg', $name));
        $this->wide[$name] = resolve(TenancyManager::class)->runFor($account, null, fn () => $host->scope());
        $this->wideProof[$name] = resolve(TenancyManager::class)->runFor($account, null, fn () => resolve(StoredWorkIssuer::class)->issue($this->wide[$name], 'test.pg', 'wide-'.$name));
    }

});

afterEach(function (): void {
    resolve(TenancyManager::class)->end();
    DB::purge('tenant');
    foreach ($this->databases as $database) {
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS '.$database);
    }
    File::deleteDirectory($this->storageRoot);
});

it('clears previous workspace for account-wide work and refuses unavailable narrowing', function (): void {
    $runner = resolve(StoredWorkRunner::class);
    $runner->runStored($this->scopes['archive'], $this->proofs['archive'], function () use ($runner): void {
        $runner->runStored($this->wide['archive'], $this->wideProof['archive'], function (): void {
            expect(resolve(TenancyManager::class)->context()->workspace)->toBeNull();
        });
        expect(resolve(TenancyManager::class)->context()->workspace->slug)->toBe('archive');
    });
    $wire = resolve(DataScopeCodec::class)->encode($this->scopes['archive']);
    $wire['workspace_id'] = (string) Str::uuid();
    $unavailable = resolve(DataScopeCodec::class)->decode($wire);
    expect(fn () => $runner->runStored($unavailable, $this->proofs['archive'], static fn () => throw new LogicException))->toThrow(ScopeContextMismatch::class)
        ->and(resolve(TenancyManager::class)->context())->toBeNull();
});

it('restores stored work on distinct physical databases across success, exception and retry', function (): void {
    $runner = resolve(StoredWorkRunner::class);
    $disk = $runner->runStored($this->scopes['archive'], $this->proofs['archive'], function () {
        expect(DB::table('context_sentinel')->value('name'))->toBe('archive');
        $disk = resolve(TenantStorage::class)->disk();
        $disk->put('document.txt', 'archive');

        return $disk;
    });
    expect(DB::table('context_sentinel')->value('name'))->toBe('root')
        ->and(fn () => $disk->get('document.txt'))->toThrow(CentralContextException::class);
    $runner->runStored($this->scopes['archive'], $this->proofs['archive'], function () use ($runner, $disk): void {
        expect(fn () => $runner->runStored($this->scopes['lab'], $this->proofs['lab'], function () use ($disk): void {
            expect(DB::table('context_sentinel')->value('name'))->toBe('lab')
                ->and(resolve(TenantStorage::class)->disk()->exists('document.txt'))->toBeFalse()
                ->and(fn () => $disk->get('document.txt'))->toThrow(StorageOperationFailed::class);

            throw new RuntimeException('interrupted');
        }))->toThrow(RuntimeException::class, 'interrupted');
        expect(DB::table('context_sentinel')->value('name'))->toBe('archive')
            ->and($disk->get('document.txt'))->toBe('archive');
    });
    expect(resolve(TenancyManager::class)->context())->toBeNull()->and(DB::table('context_sentinel')->value('name'))->toBe('root');
});
