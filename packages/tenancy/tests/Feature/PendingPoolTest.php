<?php

declare(strict_types=1);

use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Account\Internal\StanclTenantDatabaseProvisioner;
use CoreX\Tenancy\Provisioning\Exceptions\TemplateIntegrityException;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_PENDING = __DIR__.'/../../database/migrations/root';

const PENDING_TEMPLATE_DB = 'tpl_tenant_v1_pending_test';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_PENDING),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    // Golden-template fixture, same one ProvisioningTest (P2.3) uses.
    DB::statement('DROP DATABASE IF EXISTS '.PENDING_TEMPLATE_DB);
    DB::statement('CREATE DATABASE '.PENDING_TEMPLATE_DB);

    $probe = pendingConnectTo(PENDING_TEMPLATE_DB);
    $fixtureSql = file_get_contents(__DIR__.'/../Stubs/tpl_tenant_v1/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', (string) $fixtureSql))) as $statement) {
        $probe->statement($statement);
    }
    DB::disconnect('probe_'.PENDING_TEMPLATE_DB);

    DB::statement('ALTER DATABASE '.PENDING_TEMPLATE_DB.' WITH IS_TEMPLATE true');
});

afterEach(function (): void {
    DB::purge('pgsql');

    // Every pending/claimed account this test cloned a real database for —
    // drop them all instead of tracking names one by one.
    foreach (DB::table('root_accounts')->pluck('db_name') as $db) {
        DB::statement('DROP DATABASE IF EXISTS "'.$db.'"');
    }

    DB::statement('ALTER DATABASE '.PENDING_TEMPLATE_DB.' WITH IS_TEMPLATE false');
    DB::statement('DROP DATABASE IF EXISTS '.PENDING_TEMPLATE_DB);
});

/** Fresh Laravel connection bound to an arbitrary already-created database on the same PG server. */
function pendingConnectTo(string $database): Connection
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

function makePendingClusterRow(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('root_clusters')->insert(array_merge([
        'id' => $id,
        'name' => 'cell-'.$id,
        'status' => 'active',
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        // Pre-existing PG role assumed (cell-provisioning is out of scope
        // here, same allowance as P2.3's ProvisioningTest).
        'app_db_user' => 'corex',
        'template_version' => 1,
        'pending_pool_size' => 3,
    ], $overrides));

    return $id;
}

function makePendingTemplateRow(int $version = 1, string $dbName = PENDING_TEMPLATE_DB, bool $trusted = true): void
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

    if ($trusted) {
        TestCase::trustTemplate(templateId: $templateId, database: $dbName);
    }
}

function pendingPool(): PendingPool
{
    $templateIntegrityVerifier = app(TemplateIntegrityVerifier::class);

    return new PendingPool(
        connections: app(ConnectionResolverInterface::class),
        centralConnection: 'pgsql',
        provisioner: new StanclTenantDatabaseProvisioner(
            events: app(Dispatcher::class),
            centralConnection: 'pgsql',
            templateIntegrityVerifier: $templateIntegrityVerifier,
        ),
        templateIntegrityVerifier: $templateIntegrityVerifier,
    );
}

it('creates pending болванки with cloned databases, status=pending, slug/owner NULL (AC-4)', function (): void {
    $clusterId = makePendingClusterRow();
    makePendingTemplateRow();

    $ids = pendingPool()->createPending(3, $clusterId);

    expect($ids)->toHaveCount(3);

    $rows = DB::table('root_accounts')->whereIn('id', $ids)->get();
    expect($rows)->toHaveCount(3);

    foreach ($rows as $row) {
        expect($row->status)->toBe('pending')
            ->and($row->slug)->toBeNull()
            ->and($row->owner_identity_id)->toBeNull();

        $exists = DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$row->db_name]);
        expect($exists)->not->toBeNull();
    }

    $cluster = DB::table('root_clusters')->where('id', $clusterId)->first();
    expect($cluster->db_count)->toBe(3);
});

it('pullPending() claims a болванка (status→provisioning); db_count is unaffected by the claim', function (): void {
    $clusterId = makePendingClusterRow();
    makePendingTemplateRow();
    pendingPool()->createPending(2, $clusterId);

    $countBefore = DB::table('root_clusters')->where('id', $clusterId)->value('db_count');

    $account = pendingPool()->pullPending();

    expect($account)->toBeInstanceOf(Account::class);

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('provisioning')
        ->and($raw->slug)->toBeNull()
        ->and($raw->owner_identity_id)->toBeNull();

    $countAfter = DB::table('root_clusters')->where('id', $clusterId)->value('db_count');
    expect($countAfter)->toBe($countBefore);
    expect(DB::table('root_accounts')->where('status', 'pending')->count())->toBe(1);
});

it('pullPending() picks the least-loaded cell (ORDER BY db_count)', function (): void {
    $busyCluster = makePendingClusterRow(['db_count' => 10]);
    $quietCluster = makePendingClusterRow(['db_count' => 1]);
    makePendingTemplateRow();

    pendingPool()->createPending(1, $busyCluster);
    pendingPool()->createPending(1, $quietCluster);

    $account = pendingPool()->pullPending();

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->cluster_id)->toBe($quietCluster);
});

it('two concurrent pullPending() callers never claim the same болванка (SKIP LOCKED, 0 collisions)', function (): void {
    $clusterId = makePendingClusterRow();
    makePendingTemplateRow();
    $ids = pendingPool()->createPending(2, $clusterId);

    // Hold a row-lock on one pending account from a second, independent PG
    // session (uncommitted transaction) so pullPending() from the main
    // session is forced to SKIP it and claim the other one.
    $lockedId = $ids[0];
    $centralDatabase = (string) config('database.connections.pgsql.database');
    $lockConn = pendingConnectTo($centralDatabase);
    $lockConn->beginTransaction();
    $lockConn->select('SELECT id FROM root_accounts WHERE id = ? FOR UPDATE', [$lockedId]);

    $claimed = pendingPool()->pullPending();

    expect($claimed->id)->not->toBe($lockedId);

    $lockConn->rollBack();
    DB::disconnect('probe_'.$centralDatabase);
});

it('empty pool falls back to a synchronous clone under the 10s threshold (AC-5)', function (): void {
    $clusterId = makePendingClusterRow();
    makePendingTemplateRow();

    $start = microtime(true);
    $account = pendingPool()->pullPending();
    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeLessThan(10.0);

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('provisioning')
        ->and($raw->cluster_id)->toBe($clusterId);

    $exists = DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$raw->db_name]);
    expect($exists)->not->toBeNull();

    $cluster = DB::table('root_clusters')->where('id', $clusterId)->first();
    expect($cluster->db_count)->toBe(1);
});

it('pending-clear removes stale болванки by template_version (DROP+DELETE)', function (): void {
    $clusterId = makePendingClusterRow();
    makePendingTemplateRow(1);

    $staleIds = pendingPool()->createPending(2, $clusterId);
    $countBefore = DB::table('root_clusters')->where('id', $clusterId)->value('db_count');

    $cleared = pendingPool()->clearPending(templateVersion: 1);

    expect($cleared)->toBe(2)
        ->and(DB::table('root_accounts')->whereIn('id', $staleIds)->count())->toBe(0);

    $cluster = DB::table('root_clusters')->where('id', $clusterId)->first();
    expect($cluster->db_count)->toBe($countBefore - 2);
});

it('clearPending() requires at least one filter to avoid nuking the whole pool', function (): void {
    expect(fn () => pendingPool()->clearPending())->toThrow(RuntimeException::class);
});

it('does not drop a pending account claimed after the cleanup scan', function (): void {
    $cluster = makePendingClusterRow();
    makePendingTemplateRow();
    $id = pendingPool()->createPending(1, $cluster)[0];
    $other = pendingConnectTo((string) config('database.connections.pgsql.database'));
    $interleaved = false;
    DB::listen(function ($event) use ($other, $id, &$interleaved): void {
        if (! $interleaved && str_starts_with($event->sql, 'select "id", "db_name", "cluster_id"')) {
            $interleaved = true;
            $other->table('root_accounts')->where('id', $id)->update(['status' => 'provisioning']);
        }
    });
    expect(pendingPool()->clearPending(templateVersion: 1))->toBe(0)
        ->and($interleaved)->toBeTrue();
    $row = DB::table('root_accounts')->where('id', $id)->first();
    expect($row->status)->toBe('provisioning')
        ->and(DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$row->db_name]))->not->toBeNull();
    $other->disconnect();
});

it('does not publish a pending row before provisioning completes or after it fails', function (): void {
    $cluster = makePendingClusterRow();
    makePendingTemplateRow(dbName: 'tpl_missing_remediation_test', trusted: false);
    $other = pendingConnectTo((string) config('database.connections.pgsql.database'));
    $observed = null;
    DB::listen(function ($event) use ($other, &$observed): void {
        if (str_starts_with($event->sql, 'insert into "root_accounts"')) {
            $observed = $other->table('root_accounts')->where('status', 'pending')->count();
        }
    });
    expect(fn () => pendingPool()->createPending(1, $cluster))->toThrow(TemplateIntegrityException::class);
    expect($observed)->toBeNull()->and(pendingPool()->pendingCount())->toBe(0);
    $other->disconnect();
});

it('artisan corex:tenancy:pending-create tops up to pending_pool_size, corex:tenancy:pending-clear removes stale rows', function (): void {
    $clusterId = makePendingClusterRow(['pending_pool_size' => 2]);
    makePendingTemplateRow(1);

    $this->artisan('corex:tenancy:pending-create', ['--cluster' => $clusterId])->assertExitCode(0);
    expect(DB::table('root_accounts')->where('cluster_id', $clusterId)->where('status', 'pending')->count())->toBe(2);

    // Already at pool size — a second top-up run is a no-op.
    $this->artisan('corex:tenancy:pending-create', ['--cluster' => $clusterId])->assertExitCode(0);
    expect(DB::table('root_accounts')->where('cluster_id', $clusterId)->where('status', 'pending')->count())->toBe(2);

    $this->artisan('corex:tenancy:pending-clear', ['--template-version' => 1])->assertExitCode(0);
    expect(DB::table('root_accounts')->where('cluster_id', $clusterId)->count())->toBe(0);
});
