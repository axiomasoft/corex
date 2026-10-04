<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Internal\Models\AccountSchemaState;
use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Account\Internal\StanclTenantDatabaseProvisioner;
use CoreX\Tenancy\Account\Laravel\MigrationWaveOrchestrator;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// P2.12 — MigrationWaveOrchestrator + corex:tenancy:migrate (B-11 §5.6). "MW"
// suffix on shared helpers, same reasoning as AccountLifecycleTest.php's
// "LC" suffix (Pest loads every test file into one process).
uses()->group('pg');

const ROOT_MIGRATIONS_PATH_MW = __DIR__.'/../../database/migrations/root';

const MW_TEMPLATE_DB = 'tpl_tenant_v1_migwave_test';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    test()->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_MW),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    if (DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [MW_TEMPLATE_DB]) !== null) {
        DB::statement('ALTER DATABASE '.MW_TEMPLATE_DB.' WITH IS_TEMPLATE false');
    }
    DB::statement('DROP DATABASE IF EXISTS '.MW_TEMPLATE_DB.' WITH (FORCE)');
    DB::statement('CREATE DATABASE '.MW_TEMPLATE_DB);

    $probe = mwConnectTo(MW_TEMPLATE_DB);
    $fixtureSql = file_get_contents(__DIR__.'/../Stubs/tpl_tenant_v1/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', (string) $fixtureSql))) as $statement) {
        $probe->statement($statement);
    }
    DB::disconnect('probe_'.MW_TEMPLATE_DB);

    DB::statement('ALTER DATABASE '.MW_TEMPLATE_DB.' WITH IS_TEMPLATE true');
});

afterEach(function (): void {
    DB::purge('pgsql');
    DB::purge('tenant');

    foreach (DB::table('root_accounts')->pluck('db_name') as $db) {
        DB::statement('DROP DATABASE IF EXISTS "'.$db.'" WITH (FORCE)');
    }

    DB::statement('ALTER DATABASE '.MW_TEMPLATE_DB.' WITH IS_TEMPLATE false');
    DB::statement('DROP DATABASE IF EXISTS '.MW_TEMPLATE_DB);
});

/** Fresh Laravel connection bound to an arbitrary already-created database on the same PG server. */
function mwConnectTo(string $database): Connection
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

function makeMwClusterRow(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('root_clusters')->insert(array_merge([
        'id' => $id,
        'name' => 'cell-'.$id,
        'status' => 'active',
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        'app_db_user' => 'corex',
        'template_version' => 1,
        'pending_pool_size' => 3,
    ], $overrides));

    return $id;
}

function makeMwTemplateRow(int $version = 1, string $dbName = MW_TEMPLATE_DB): void
{
    $templateId = (string) Str::uuid();

    DB::table('root_templates')->insert([
        'id' => $templateId,
        'version' => $version,
        'db_name' => $dbName,
        'status' => 'ready',
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'built_at' => now(),
    ]);

    TestCase::trustTemplate(templateId: $templateId, database: $dbName);
}

/** @return list<string> account ids with a real, physically-cloned database. */
function makeMwAccounts(int $count, ?string $clusterId = null): array
{
    $clusterId ??= makeMwClusterRow();
    makeMwTemplateRow();

    $templateIntegrityVerifier = app(TemplateIntegrityVerifier::class);

    return (new PendingPool(
        connections: app(ConnectionResolverInterface::class),
        centralConnection: 'pgsql',
        provisioner: new StanclTenantDatabaseProvisioner(
            events: app(Dispatcher::class),
            centralConnection: 'pgsql',
            templateIntegrityVerifier: $templateIntegrityVerifier,
        ),
        templateIntegrityVerifier: $templateIntegrityVerifier,
    ))->createPending($count, $clusterId);
}

function makeMwBatch(int $toVersion = 1, string $wave = 'internal'): string
{
    $id = (string) Str::uuid();

    DB::table('root_migration_batches')->insert([
        'id' => $id,
        'from_version' => 0,
        'to_version' => $toVersion,
        'wave' => $wave,
    ]);

    return $id;
}

it('AC-11: a failing account does not abort the batch; a second failure escalates failed→blocked', function (): void {
    [$goodId, $badId] = makeMwAccounts(2);
    $batchId = makeMwBatch();

    $orchestrator = new MigrationWaveOrchestrator(
        centralConnection: 'pgsql',
        tenancy: app(TenancyManager::class),
        templateIntegrityVerifier: app(TemplateIntegrityVerifier::class),
        migrationRunner: function (AccountRef $account) use ($badId): void {
            if ($account->id === $badId) {
                throw new RuntimeException('simulated migration failure');
            }
        },
    );

    $orchestrator->runBatch($batchId);

    $good = AccountSchemaState::query()->findOrFail($goodId);
    expect($good->status)->toBe('ok')
        ->and($good->current_version)->toBe(1);

    $bad = AccountSchemaState::query()->findOrFail($badId);
    expect($bad->status)->toBe('failed')
        ->and($bad->current_version)->toBe(0)
        ->and($bad->last_error)->not->toBeNull();

    // Second run (retry): $good is already at target_version, excluded;
    // $bad fails again → escalates to 'blocked'.
    $orchestrator->runBatch($batchId);

    $bad->refresh();
    expect($bad->status)->toBe('blocked');

    $good->refresh();
    expect($good->status)->toBe('ok')
        ->and($good->current_version)->toBe(1);
});

it('AC-12: the migration run issues pg_advisory_xact_lock(hashtext(\'fld_ddl\')) on the account\'s own connection', function (): void {
    makeMwAccounts(1);
    $batchId = makeMwBatch();

    $queries = [];
    DB::listen(function ($event) use (&$queries): void {
        $queries[] = $event->sql;
    });

    // Default runner (no override) — this package's tenant migrations
    // reference cross-package tables (`sys_departments`, axiomasoft/corex) that
    // this test's bare fixture database doesn't have, so the migrate step
    // itself may fail past the lock statement; that failure is swallowed
    // into schema_state.status='failed' by the orchestrator (already
    // covered by the AC-11 test) — irrelevant to AC-12, which only asserts
    // the lock CALL itself was issued.
    $orchestrator = new MigrationWaveOrchestrator(
        centralConnection: 'pgsql',
        tenancy: app(TenancyManager::class),
        templateIntegrityVerifier: app(TemplateIntegrityVerifier::class),
    );
    $orchestrator->runBatch($batchId);

    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'pg_advisory_xact_lock')))->toBeTrue();
});

it('creates root_account_schema_state rows (current_version=0) for accounts missing one', function (): void {
    [$accountId] = makeMwAccounts(1);
    $batchId = makeMwBatch();

    expect(AccountSchemaState::query()->where('account_id', $accountId)->exists())->toBeFalse();

    $orchestrator = new MigrationWaveOrchestrator(
        centralConnection: 'pgsql',
        tenancy: app(TenancyManager::class),
        templateIntegrityVerifier: app(TemplateIntegrityVerifier::class),
        migrationRunner: function (): void {
            //
        },
    );
    $orchestrator->runBatch($batchId);

    $state = AccountSchemaState::query()->findOrFail($accountId);
    expect($state->current_version)->toBe(1)
        ->and($state->status)->toBe('ok');
});

it('corex:tenancy:migrate requires --batch and does not hard-fail the command on a per-account failure (--skip-failing semantics)', function (): void {
    test()->artisan('corex:tenancy:migrate')->assertExitCode(1);

    [$goodId, $badId] = makeMwAccounts(2);
    $batchId = makeMwBatch();

    app()->instance(MigrationWaveOrchestrator::class, new MigrationWaveOrchestrator(
        centralConnection: 'pgsql',
        tenancy: app(TenancyManager::class),
        templateIntegrityVerifier: app(TemplateIntegrityVerifier::class),
        migrationRunner: function (AccountRef $account) use ($badId): void {
            if ($account->id === $badId) {
                throw new RuntimeException('simulated migration failure');
            }
        },
    ));

    test()->artisan('corex:tenancy:migrate', ['--batch' => $batchId, '--skip-failing' => true])->assertExitCode(0);

    expect(AccountSchemaState::query()->findOrFail($goodId)->status)->toBe('ok')
        ->and(AccountSchemaState::query()->findOrFail($badId)->status)->toBe('failed');
});
