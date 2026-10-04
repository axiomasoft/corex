<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenantDatabaseLifecycle;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Exceptions\ImpersonationRestrictedException;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Tenancy\Account\Events\AccountPurged;
use CoreX\Tenancy\Account\Events\AccountSuspended;
use CoreX\Tenancy\Account\Events\GraceStarted;
use CoreX\Tenancy\Account\Events\TrialStarted;
use CoreX\Tenancy\Account\Internal\DatabaseTenantProvisioner;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycle;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycleFsm;
use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy;
use CoreX\Tenancy\Laravel\Support\HostResolverCacheInvalidator;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Internal\DatabaseTenantLifecycle;
use CoreX\Tenancy\Runtime\Internal\HostAccountResolver;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Clock\ClockInterface;

// P2.12 — AccountLifecycle FSM (B-11 §5.1). Reuses PendingPoolTest's
// physical-clone bootstrap style with an "LC"-suffixed const/helper set
// (Pest loads every test file into the same process — a shared name would
// fatal-collide with PendingPoolTest.php).
uses()->group('pg');

const ROOT_MIGRATIONS_PATH_LC = __DIR__.'/../../database/migrations/root';

const LC_TEMPLATE_DB = 'tpl_tenant_v1_lifecycle_test';

beforeEach(function (): void {
    // Pin the neutral actor path used by this lifecycle fixture regardless
    // of other provider bindings in the package Testbench application.
    app()->singleton(ImpersonationService::class, NullImpersonationService::class);

    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    test()->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_LC),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    DB::statement('DROP DATABASE IF EXISTS '.LC_TEMPLATE_DB);
    DB::statement('CREATE DATABASE '.LC_TEMPLATE_DB);

    $probe = lcConnectTo(LC_TEMPLATE_DB);
    $fixtureSql = file_get_contents(__DIR__.'/../Stubs/tpl_tenant_v1/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', (string) $fixtureSql))) as $statement) {
        $probe->statement($statement);
    }
    DB::disconnect('probe_'.LC_TEMPLATE_DB);

    DB::statement('ALTER DATABASE '.LC_TEMPLATE_DB.' WITH IS_TEMPLATE true');
});

afterEach(function (): void {
    DB::purge('pgsql');

    foreach (DB::table('root_accounts')->pluck('db_name') as $db) {
        DB::statement('DROP DATABASE IF EXISTS "'.$db.'"');
    }

    DB::statement('ALTER DATABASE '.LC_TEMPLATE_DB.' WITH IS_TEMPLATE false');
    DB::statement('DROP DATABASE IF EXISTS '.LC_TEMPLATE_DB);
});

/** Fresh Laravel connection bound to an arbitrary already-created database on the same PG server. */
function lcConnectTo(string $database): Connection
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

function makeLcClusterRow(array $overrides = []): string
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

function makeLcTemplateRow(int $version = 1, string $dbName = LC_TEMPLATE_DB): void
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

function makeLcIdentityRow(): string
{
    $id = (string) Str::uuid();

    DB::table('root_identities')->insert([
        'id' => $id,
        'email' => $id.'@example.test',
    ]);

    return $id;
}

function lcLifecycle(?ImpersonationService $impersonation = null, ?TenantDatabaseLifecycle $tenantDatabaseLifecycle = null): AccountLifecycle
{
    $templateIntegrityVerifier = app(TemplateIntegrityVerifier::class);

    return new AccountLifecycle(
        lifecycle: app(AccountLifecyclePolicy::class), connections: app(ConnectionResolverInterface::class), clock: app(ClockInterface::class), events: app(Dispatcher::class), connectionFactory: app(ConnectionFactory::class), hostCache: app(HostResolverCacheInvalidator::class),
        centralConnection: 'pgsql',
        pendingPool: new PendingPool(
            connections: app(ConnectionResolverInterface::class),
            centralConnection: 'pgsql',
            provisioner: new DatabaseTenantProvisioner(
                centralConnection: 'pgsql',
                templateIntegrityVerifier: $templateIntegrityVerifier,
            ),
            templateIntegrityVerifier: $templateIntegrityVerifier,
        ),
        fsm: new AccountLifecycleFsm,
        tenantDatabaseLifecycle: $tenantDatabaseLifecycle ?? new DatabaseTenantLifecycle(connections: app(ConnectionResolverInterface::class), clock: app(ClockInterface::class), events: app(Dispatcher::class), centralConnection: 'pgsql'),
        impersonationGuard: app()->makeWith(ImpersonationGuard::class, ['impersonation' => $impersonation ?? new NullImpersonationService]),
        templateIntegrityVerifier: $templateIntegrityVerifier,
    );
}

/** Claims a pending болванка all the way through activateFromPool() → trial. */
function activateLcAccount(): AccountRef
{
    $clusterId = makeLcClusterRow();
    makeLcTemplateRow();
    $templateIntegrityVerifier = app(TemplateIntegrityVerifier::class);
    (new PendingPool(
        connections: app(ConnectionResolverInterface::class),
        centralConnection: 'pgsql',
        provisioner: new DatabaseTenantProvisioner(
            centralConnection: 'pgsql',
            templateIntegrityVerifier: $templateIntegrityVerifier,
        ),
        templateIntegrityVerifier: $templateIntegrityVerifier,
    ))->createPending(1, $clusterId);

    $ownerId = makeLcIdentityRow();
    $slug = 'acme-'.Str::random(6);

    return lcLifecycle()->activateFromPool($slug, $ownerId, 'crm');
}

it('activateFromPool() claims, sets slug/owner/vertical, transitions provisioning→trial and provisions the platform domain (AC-13)', function (): void {
    Event::fake([TrialStarted::class]);

    $account = activateLcAccount();

    expect($account->status)->toBe('trial');

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('trial')
        ->and($raw->slug)->toBe($account->slug)
        ->and($raw->owner_identity_id)->not->toBeNull()
        ->and($raw->vertical_code)->toBe('crm')
        ->and($raw->trial_ends_at)->not->toBeNull();

    $domain = DB::table('root_domains')->where('account_id', $account->id)->first();
    expect($domain)->not->toBeNull()
        ->and($domain->host)->toBe($account->slug.'.'.config('corex-tenancy.platform_domain'))
        ->and($domain->type)->toBe('platform')
        ->and($domain->verified_at)->not->toBeNull()
        ->and($domain->tls_status)->toBe('issued');

    $events = DB::table('root_account_events')->where('account_id', $account->id)->get();
    expect($events)->toHaveCount(1)
        ->and($events->first()->event)->toBe('account.trial_started');

    Event::assertDispatched(TrialStarted::class, fn (TrialStarted $e): bool => $e->accountId === $account->id);
});

it('invalid transition throws and writes no root_account_events row', function (): void {
    $account = activateLcAccount();

    // trial → exporting is not a valid edge (only grace → exporting is).
    expect(fn () => lcLifecycle()->requestExport($account))->toThrow(RuntimeException::class);

    expect(DB::table('root_account_events')->where('account_id', $account->id)->where('event', 'account.export_requested')->count())->toBe(0);
});

it('suspend() writes account.suspended + fires AccountSuspended, resume() returns to active', function (): void {
    Event::fake([AccountSuspended::class]);

    $account = activateLcAccount();
    lcLifecycle()->activate($account);

    lcLifecycle()->suspend($account, 'nonpayment');

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('suspended')
        ->and($raw->suspended_at)->not->toBeNull();

    expect(DB::table('root_account_events')->where('account_id', $account->id)->where('event', 'account.suspended')->count())->toBe(1);
    Event::assertDispatched(AccountSuspended::class, fn (AccountSuspended $e): bool => $e->accountId === $account->id && $e->reason === 'nonpayment');

    lcLifecycle()->resume($account);

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('active')
        ->and($raw->suspended_at)->toBeNull();
});

it('startGrace() sets grace_until/purge_after and fires GraceStarted', function (): void {
    Event::fake([GraceStarted::class]);

    $account = activateLcAccount();
    lcLifecycle()->activate($account);

    lcLifecycle()->startGrace($account);

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('grace')
        ->and($raw->grace_until)->not->toBeNull()
        ->and($raw->purge_after)->not->toBeNull();

    Event::assertDispatched(GraceStarted::class, fn (GraceStarted $e): bool => $e->accountId === $account->id);
});

it('purge() before a ready export throws and does NOT drop the database (AC-7)', function (): void {
    $account = activateLcAccount();
    lcLifecycle()->activate($account);
    lcLifecycle()->startGrace($account);

    // Move to `exporting` directly (bypassing requestExport()'s synchronous
    // writer) so the export row stays `running`, not `ready`.
    DB::table('root_accounts')->where('id', $account->id)->update(['status' => 'exporting']);
    DB::table('root_account_exports')->insert([
        'id' => (string) Str::uuid(),
        'account_id' => $account->id,
        'kind' => 'offboarding',
        'status' => 'running',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $spyDrop = Mockery::mock(TenantDatabaseLifecycle::class);
    $spyDrop->shouldNotReceive('drop');
    $spyDrop->shouldReceive('export')->never();

    expect(fn () => lcLifecycle(tenantDatabaseLifecycle: $spyDrop)->purge($account))->toThrow(ValidationException::class);

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('exporting')
        ->and($raw->deleted_at)->toBeNull();
});

it('purge() after a ready export drops the DB, soft-deletes the account, removes its domains, fires AccountPurged (AC-7)', function (): void {
    Event::fake([AccountPurged::class]);

    $account = activateLcAccount();
    lcLifecycle()->activate($account);
    lcLifecycle()->startGrace($account);

    $archive = tempnam(sys_get_temp_dir(), 'corex-export-test-');
    file_put_contents($archive, 'test archive');
    $digest = hash_file('sha256', $archive);
    $database = new DatabaseTenantLifecycle(
        connections: app(ConnectionResolverInterface::class), clock: app(ClockInterface::class), events: app(Dispatcher::class),
        centralConnection: 'pgsql',
        writer: static fn (): array => ['storage_path' => $archive, 'size_bytes' => 12],
        verifier: static fn (string $path, int $size): bool => $path === $archive && is_file($path) && is_readable($path) && filesize($path) === $size && hash_equals($digest, (string) hash_file('sha256', $path)),
    );
    $this->beforeApplicationDestroyed(static fn () => unlink($archive));
    $exportId = lcLifecycle(tenantDatabaseLifecycle: $database)->requestExport($account);
    expect(DB::table('root_account_exports')->where('id', $exportId)->value('status'))->toBe('ready');

    $dbName = DB::table('root_accounts')->where('id', $account->id)->value('db_name');
    $existsBefore = DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$dbName]);
    expect($existsBefore)->not->toBeNull();

    lcLifecycle(tenantDatabaseLifecycle: $database)->purge($account);

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('deleted')
        ->and($raw->deleted_at)->not->toBeNull();

    $existsAfter = DB::selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$dbName]);
    expect($existsAfter)->toBeNull();

    expect(DB::table('root_domains')->where('account_id', $account->id)->whereNull('deleted_at')->count())->toBe(0)
        ->and(DB::table('root_domains')->where('account_id', $account->id)->whereNotNull('deleted_at')->count())->toBeGreaterThan(0);

    Event::assertDispatched(AccountPurged::class, fn (AccountPurged $e): bool => $e->accountId === $account->id);
});

it('default export cannot become ready or authorize purge', function (): void {
    $account = activateLcAccount();
    lcLifecycle()->startGrace($account);
    expect(fn () => lcLifecycle()->requestExport($account))->toThrow(RuntimeException::class, 'No tenant export writer');
    expect(DB::table('root_account_exports')->where('account_id', $account->id)->where('status', 'ready')->count())->toBe(0);
    expect(fn () => lcLifecycle()->purge($account))->toThrow(ValidationException::class);
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('exporting');
});

it('refuses ready and DROP when an archive is unavailable', function (): void {
    $account = activateLcAccount();
    $database = new DatabaseTenantLifecycle(
        connections: app(ConnectionResolverInterface::class), clock: app(ClockInterface::class), events: app(Dispatcher::class),
        centralConnection: 'pgsql',
        writer: static fn (): array => ['storage_path' => '/missing/archive.dump', 'size_bytes' => 12],
        verifier: static fn (): bool => false,
    );
    expect(fn () => $database->export($account, 'offboarding'))->toThrow(RuntimeException::class, 'archive is unavailable');
    expect(DB::table('root_account_exports')->where('account_id', $account->id)->value('status'))->toBe('running');
    DB::table('root_account_exports')->where('account_id', $account->id)->update([
        'status' => 'ready', 'storage_path' => '/missing/archive.dump', 'size_bytes' => 12,
    ]);
    expect(fn () => $database->drop($account))->toThrow(RuntimeException::class, 'archive is unavailable');
    $name = DB::table('root_accounts')->where('id', $account->id)->value('db_name');
    expect(DB::table('pg_database')->where('datname', $name)->exists())->toBeTrue();
});

it('retries failed physical purge without publishing a terminal account state', function (): void {
    $account = activateLcAccount();
    lcLifecycle()->startGrace($account);
    DB::table('root_accounts')->where('id', $account->id)->update(['status' => 'exporting']);
    DB::table('root_account_exports')->insert(['id' => (string) Str::uuid7(), 'account_id' => $account->id, 'kind' => 'offboarding', 'status' => 'ready']);
    $database = Mockery::mock(TenantDatabaseLifecycle::class);
    $database->shouldReceive('drop')->once()->andThrow(new RuntimeException('drop failed'));
    expect(fn () => lcLifecycle(tenantDatabaseLifecycle: $database)->purge($account))->toThrow(RuntimeException::class, 'drop failed');
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('exporting')
        ->and(DB::table('root_domains')->where('account_id', $account->id)->whereNull('deleted_at')->count())->toBe(1);
    $retry = Mockery::mock(TenantDatabaseLifecycle::class);
    $retry->shouldReceive('drop')->once();
    lcLifecycle(tenantDatabaseLifecycle: $retry)->purge($account);
    lcLifecycle(tenantDatabaseLifecycle: $retry)->purge($account);
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('deleted')
        ->and(DB::table('root_account_events')->where('account_id', $account->id)->where('event', 'account.purged')->count())->toBe(1);
});

it('invalidates a warmed host cache entry only when a status transition commits', function (): void {
    config([
        'corex-tenancy.host_identification.enabled' => true,
        'corex-tenancy.host_identification.cache' => true,
        'corex-tenancy.host_identification.cache_store' => null,
    ]);
    app()->forgetInstance(TenancyOptions::class);
    $account = activateLcAccount();
    lcLifecycle()->activate($account);
    $resolver = app(HostAccountResolver::class);
    $host = $account->slug.'.'.config('corex-tenancy.platform_domain');
    $key = HostAccountResolver::key(strtolower($host));
    expect($resolver->resolve($host))->toBe($account->id)
        ->and(Cache::has($key))->toBeTrue();
    DB::beginTransaction();
    lcLifecycle()->suspend($account, 'test');
    DB::rollBack();
    expect(Cache::has($key))->toBeTrue();
    lcLifecycle()->suspend($account, 'test');
    expect(Cache::has($key))->toBeFalse();
});

it('holds the account row lock while validating and committing a transition', function (): void {
    $account = activateLcAccount();
    lcLifecycle()->activate($account);
    $other = lcConnectTo((string) config('database.connections.pgsql.database'));
    $other->beginTransaction();
    $other->statement("SET LOCAL lock_timeout = '50ms'");
    $attempted = false;
    $blocked = false;
    DB::listen(function ($event) use ($other, $account, &$attempted, &$blocked): void {
        if (! $attempted && str_starts_with($event->sql, 'select "status" from "root_accounts"')) {
            $attempted = true;

            try {
                $other->table('root_accounts')->where('id', $account->id)->update(['status' => 'grace']);
            } catch (QueryException $e) {
                $blocked = $e->getCode() === '55P03';
            }
        }
    });
    lcLifecycle()->suspend($account, 'test');
    expect($attempted)->toBeTrue()->and($blocked)->toBeTrue();
    $other->rollBack();
    $other->disconnect();
});

it('purge() under active impersonation throws ImpersonationRestrictedException and never touches the tenant DB (D151)', function (): void {
    $account = activateLcAccount();
    lcLifecycle()->activate($account);
    lcLifecycle()->startGrace($account);

    $impersonating = new class implements ImpersonationService
    {
        public function start(string $grantId, string $staffIdentityId): void {}

        public function stop(): void {}

        public function active(): ?string
        {
            return 'grant-id';
        }

        public function state(): ?ImpersonationState
        {
            return new ImpersonationState('grant-id', 'staff-id', 'target-id', new DateTimeImmutable('+1 hour'));
        }
    };

    $spyDrop = Mockery::mock(TenantDatabaseLifecycle::class);
    $spyDrop->shouldNotReceive('drop');
    $spyDrop->shouldNotReceive('export');

    expect(fn () => lcLifecycle(impersonation: $impersonating, tenantDatabaseLifecycle: $spyDrop)->purge($account))
        ->toThrow(ImpersonationRestrictedException::class);

    $raw = DB::table('root_accounts')->where('id', $account->id)->first();
    expect($raw->status)->toBe('grace');
});

it('rejects impersonated signup before claiming or provisioning a pool account', function (): void {
    $guard = Mockery::mock(ImpersonationService::class);
    $guard->shouldReceive('active')->andReturn('grant-id');
    $guard->shouldReceive('state')->andReturn(new ImpersonationState('grant-id', 'staff-id', 'target-id', new DateTimeImmutable('+1 hour')));
    // No cluster exists: touching the pool would throw a provisioning error
    // instead of the required authorization error.
    expect(fn () => lcLifecycle($guard)->activateFromPool('forbidden', (string) Str::uuid7(), 'crm'))
        ->toThrow(ImpersonationRestrictedException::class);
    expect(DB::table('root_accounts')->count())->toBe(0);
});
