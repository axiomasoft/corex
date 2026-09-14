<?php

declare(strict_types=1);

use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Database\TemplateIntegrityException;
use CoreX\Tenancy\Models\Account;
use CoreX\Tenancy\Provisioning\ClusterAccountConnectionResolver;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseProvisioner;
use CoreX\Tenancy\ProvisionParams;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_PROVISIONING = __DIR__.'/../../database/migrations/root';

const TEMPLATE_DB = 'tpl_tenant_v1_test';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_PROVISIONING),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    // Golden-template fixture: a real (tiny) database with data, set as a
    // Postgres template so `CREATE DATABASE … TEMPLATE` can clone it.
    DB::statement('DROP DATABASE IF EXISTS '.TEMPLATE_DB);
    DB::statement('CREATE DATABASE '.TEMPLATE_DB);

    $probe = connectTo(TEMPLATE_DB);
    $fixtureSql = file_get_contents(__DIR__.'/../Stubs/tpl_tenant_v1/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', (string) $fixtureSql))) as $statement) {
        $probe->statement($statement);
    }
    DB::disconnect('probe_'.TEMPLATE_DB);

    DB::statement('ALTER DATABASE '.TEMPLATE_DB.' WITH IS_TEMPLATE true');
});

afterEach(function (): void {
    DB::purge('pgsql');

    foreach ($GLOBALS['__provisioning_test_dbs'] ?? [] as $db) {
        DB::statement('DROP DATABASE IF EXISTS '.$db);
    }
    $GLOBALS['__provisioning_test_dbs'] = [];

    DB::statement('ALTER DATABASE '.TEMPLATE_DB.' WITH IS_TEMPLATE false');
    DB::statement('DROP DATABASE IF EXISTS '.TEMPLATE_DB);
});

function trackDatabase(string $name): void
{
    $GLOBALS['__provisioning_test_dbs'][] = $name;
}

/** Fresh Laravel connection bound to an arbitrary already-created database on the same PG server. */
function connectTo(string $database): Connection
{
    $name = 'probe_'.$database;

    config(["database.connections.{$name}" => [
        'driver' => 'pgsql',
        'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
        'port' => getenv('PGSQL_PORT') ?: '5434',
        'database' => $database,
        'username' => getenv('PGSQL_USERNAME') ?: 'corex',
        'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
    ]]);

    DB::purge($name);

    return DB::connection($name);
}

function makeClusterRow(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('root_clusters')->insert(array_merge([
        'id' => $id,
        'name' => 'cell-'.$id,
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        // Pre-existing PG role assumed (cell-provisioning is out of P2.3
        // scope) — 'corex' is the superuser the docker fixture already has.
        'app_db_user' => 'corex',
        'template_version' => 1,
    ], $overrides));

    return $id;
}

function makeReadyTemplate(int $version, string $dbName = TEMPLATE_DB): void
{
    $id = (string) Str::uuid();
    DB::table('root_templates')->insert([
        'id' => $id,
        'version' => $version,
        'db_name' => $dbName,
        'status' => 'ready',
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'built_at' => now(),
    ]);
    TestCase::trustTemplate(templateId: $id, database: $dbName);
}

function makeAccountRow(string $clusterId, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('root_accounts')->insert(array_merge([
        'id' => $id,
        'cluster_id' => $clusterId,
        'db_name' => 'acc_'.str_replace('-', '', $id),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
    ], $overrides));

    return $id;
}

it('creates an Account with a UUIDv7 key and typed custom columns', function (): void {
    $clusterId = makeClusterRow();

    $account = Account::on('pgsql')->create([
        'cluster_id' => $clusterId,
        'db_name' => 'acc_uuidv7_test',
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'meta_note' => 'falls into jsonb data',
    ]);

    expect($account->id)->toBeString()
        ->and(Str::isUuid($account->id))->toBeTrue()
        // UUIDv7: version nibble is '7'.
        ->and($account->id[14])->toBe('7');

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('pending')
        ->and($raw->data)->toContain('meta_note');
});

it('provisions a database by cloning the golden template via STRATEGY WAL_LOG, without Migrate/Seed', function (): void {
    $clusterId = makeClusterRow();
    makeReadyTemplate(1);
    $accountId = makeAccountRow($clusterId);

    $targetDb = DB::table('root_accounts')->where('id', $accountId)->value('db_name');
    trackDatabase($targetDb);

    (new StanclTenantDatabaseProvisioner('pgsql'))->provision(new ProvisionParams(
        accountId: $accountId,
        clusterId: $clusterId,
        templateVersion: 1,
        embeddingModel: 'bge-m3',
        embeddingDim: 1024,
    ));

    $exists = DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$targetDb]);
    expect($exists)->not->toBeNull();

    // STRATEGY WAL_LOG actually copied the golden template's data.
    $clone = connectTo($targetDb);
    expect($clone->table('smoke_marker')->value('note'))->toBe('golden-template-clone');

    // No tenant migrations table — MigrateDatabase/SeedDatabase never ran.
    expect($clone->getSchemaBuilder()->hasTable('migrations'))->toBeFalse();

    DB::disconnect('probe_'.$targetDb);
});

it('re-provisioning an already-created database is idempotent (replayable pipeline)', function (): void {
    $clusterId = makeClusterRow();
    makeReadyTemplate(1);
    $accountId = makeAccountRow($clusterId);

    $params = new ProvisionParams(
        accountId: $accountId,
        clusterId: $clusterId,
        templateVersion: 1,
        embeddingModel: 'bge-m3',
        embeddingDim: 1024,
    );

    $targetDb = DB::table('root_accounts')->where('id', $accountId)->value('db_name');
    trackDatabase($targetDb);

    $provisioner = new StanclTenantDatabaseProvisioner('pgsql');
    $provisioner->provision($params);
    $provisioner->provision($params);

    $count = DB::table('pg_database')->where('datname', $targetDb)->count();
    expect($count)->toBe(1);
});

it('refuses to clone a template that is not ready (template-parity gate, D24)', function (): void {
    $clusterId = makeClusterRow();

    DB::table('root_templates')->insert([
        'id' => (string) Str::uuid(),
        'version' => 2,
        'db_name' => TEMPLATE_DB,
        'status' => 'building',
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
    ]);

    $accountId = makeAccountRow($clusterId, ['template_version' => 2]);

    expect(fn () => (new StanclTenantDatabaseProvisioner('pgsql'))->provision(new ProvisionParams(
        accountId: $accountId,
        clusterId: $clusterId,
        templateVersion: 2,
        embeddingModel: 'bge-m3',
        embeddingDim: 1024,
    )))->toThrow(RuntimeException::class);

    $targetDb = DB::table('root_accounts')->where('id', $accountId)->value('db_name');
    $exists = DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$targetDb]);
    expect($exists)->toBeNull();
});

it('denies an unsealed ready template before it writes provisioning parameters', function (): void {
    $clusterId = makeClusterRow(['template_version' => 2]);
    DB::table('root_templates')->insert([
        'id' => (string) Str::uuid(),
        'version' => 2,
        'db_name' => TEMPLATE_DB,
        'status' => 'ready',
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
    ]);
    $accountId = makeAccountRow($clusterId, [
        'template_version' => 2,
        'embedding_model' => 'unchanged',
        'embedding_dim' => 1,
    ]);
    $targetDb = DB::table('root_accounts')->where('id', $accountId)->value('db_name');

    expect(fn () => (new StanclTenantDatabaseProvisioner('pgsql'))->provision(new ProvisionParams(
        accountId: $accountId,
        clusterId: $clusterId,
        templateVersion: 2,
        embeddingModel: 'bge-m3',
        embeddingDim: 1024,
    )))->toThrow(TemplateIntegrityException::class);

    $account = DB::table('root_accounts')->where('id', $accountId)->first();
    expect($account->embedding_model)->toBe('unchanged')
        ->and($account->embedding_dim)->toBe(1)
        ->and(DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$targetDb]))->toBeNull();
});

it('fixes embedding_model/dim and other provisioning params onto the account row (AC-10)', function (): void {
    $clusterId = makeClusterRow();
    makeReadyTemplate(1);
    $accountId = makeAccountRow($clusterId, [
        'embedding_model' => 'placeholder',
        'embedding_dim' => 1,
        'template_version' => 1,
    ]);
    trackDatabase(DB::table('root_accounts')->where('id', $accountId)->value('db_name'));

    (new StanclTenantDatabaseProvisioner('pgsql'))->provision(new ProvisionParams(
        accountId: $accountId,
        clusterId: $clusterId,
        templateVersion: 1,
        embeddingModel: 'bge-m3',
        embeddingDim: 1024,
        verticalCode: 'crm',
        locale: 'en',
    ));

    $row = DB::table('root_accounts')->where('id', $accountId)->first();

    expect($row->embedding_model)->toBe('bge-m3')
        ->and($row->embedding_dim)->toBe(1024)
        ->and($row->vertical_code)->toBe('crm')
        ->and($row->locale)->toBe('en');
});

it('resolves the tenant connection from root_clusters+db_name and follows cluster_id (AC-9)', function (): void {
    $clusterA = makeClusterRow(['pgbouncer_host' => 'pgbouncer-a.local']);
    $clusterB = makeClusterRow(['pgbouncer_host' => 'pgbouncer-b.local']);
    $accountId = makeAccountRow($clusterA);

    $resolver = new ClusterAccountConnectionResolver('pgsql');
    $accountRef = new AccountRef(
        id: $accountId,
        slug: 'irrelevant',
        status: 'pending',
        features: [],
        limits: [],
    );

    $config = $resolver->resolve($accountRef);
    expect($config->host)->toBe('pgbouncer-a.local');

    DB::table('root_accounts')->where('id', $accountId)->update(['cluster_id' => $clusterB]);

    $config = $resolver->resolve($accountRef);
    expect($config->host)->toBe('pgbouncer-b.local');
});
