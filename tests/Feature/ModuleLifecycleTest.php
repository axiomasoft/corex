<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaLifecycle;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Database\Internal\TaggedSchemaSets;
use CoreX\Features\Contracts\FeatureFlags;
use CoreX\Features\Internal\DatabaseFeatureFlags;
use CoreX\Messaging\Events\DomainEvent;
use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistryCache;
use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Contracts\RecordsRegistrar;
use CoreX\Modules\Data\CompiledModule;
use CoreX\Modules\Enums\DisableReason;
use CoreX\Modules\Enums\ModuleState;
use CoreX\Modules\Events\ModuleArchived;
use CoreX\Modules\Events\ModuleDisabled;
use CoreX\Modules\Events\ModuleEnabled;
use CoreX\Modules\Events\ModulePurged;
use CoreX\Modules\Events\ModuleUpgraded;
use CoreX\Modules\Exceptions\HasEnabledDependentsException;
use CoreX\Modules\Exceptions\InvalidStateTransitionException;
use CoreX\Modules\Exceptions\MissingDependencyException;
use CoreX\Modules\Exceptions\ModuleEnableFailedException;
use CoreX\Modules\Exceptions\ModuleUpgradeFailedException;
use CoreX\Modules\Internal\Discovery\ModuleDiscovery;
use CoreX\Modules\Internal\Lifecycle\DatabaseModuleLifecycle;
use CoreX\Modules\Internal\Lifecycle\DatabaseModuleStateReader;
use CoreX\Modules\Internal\Lifecycle\DatabaseRecordsRegistrar;
use CoreX\Modules\Internal\Registry\CompiledModuleRegistry;
use CoreX\Modules\Internal\Registry\RepositoryModuleRegistryCache;
use CoreX\Modules\Internal\Support\ModulesOptions;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Tests\Fixtures\Schema\FixtureSchemaSets;
use CoreX\Tests\Stubs\Lifecycle\StubBaseModule;
use CoreX\Tests\Stubs\Lifecycle\StubDependentModule;
use CoreX\Tests\Stubs\Lifecycle\StubFailingModule;
use CoreX\Tests\Stubs\Lifecycle\StubFeatureFlagModule;
use CoreX\Tests\Stubs\Lifecycle\StubFkModule;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;

uses()->group('pg');

const LIFECYCLE_TEST_CONNECTION = 'pgsql';

function lifecyclePg(): Connection
{
    return DB::connection(LIFECYCLE_TEST_CONNECTION);
}

function lifecycleStubsPath(string $dir): string
{
    return realpath(__DIR__.'/../Stubs/Lifecycle/migrations/'.$dir);
}

/**
 * Pinned to LIFECYCLE_TEST_CONNECTION explicitly, never the ambient default
 * (Pest runs every test in one PHP process; mutating config('database.default')
 * leaks across tests via Laravel's static migration-repository/connection
 * caches — pinning the connection everywhere avoids that class of bug).
 *
 * @param  array<string, string>  $migrationPaths
 * @param  array<string, string>  $codeVersions
 */
function lifecycleFor(array $migrationPaths = [], array $codeVersions = []): DatabaseModuleLifecycle
{
    return new DatabaseModuleLifecycle(
        registry: resolve(ModuleRegistry::class),
        discovery: resolve(ModuleDiscovery::class),
        records: app()->make(DatabaseRecordsRegistrar::class, ['connection' => LIFECYCLE_TEST_CONNECTION]),
        container: app(),
        tenantContextResolver: resolve(TenantContextResolver::class), connections: resolve(ConnectionResolverInterface::class), events: resolve(Dispatcher::class), clock: resolve(ClockInterface::class),
        codeVersions: $codeVersions,
        migrationPaths: $migrationPaths,
        connection: LIFECYCLE_TEST_CONNECTION,
    );
}

/**
 * Resolve the lifecycle THROUGH the container (not a hand-built constructor):
 * connection comes from the Options snapshot captured at provider registration,
 * codeVersions/migrationPaths from the
 * `corex.modules.wiring` binding the ModulesServiceProvider populates from
 * installed.json. Tests override that binding with stub paths to prove the
 * container assembly actually wires migrations (C: empty autowired arrays).
 *
 * @param  array<string, string>  $migrationPaths
 * @param  array<string, string>  $codeVersions
 */
function lifecycleFromContainer(array $migrationPaths = [], array $codeVersions = []): ModuleLifecycle
{
    app()->instance('corex.modules.wiring', ['codeVersions' => $codeVersions, 'migrationPaths' => $migrationPaths]);
    app()->forgetInstance(RecordsRegistrar::class);
    app()->forgetInstance(ModuleLifecycle::class);

    return resolve(ModuleLifecycle::class);
}

/** A second, independent session on the same pg database — models a concurrent caller. */
function lifecycleSession(string $name): Connection
{
    /** @var array<string, mixed> $config */
    $config = config('database.connections.'.LIFECYCLE_TEST_CONNECTION);
    config(['database.connections.'.$name => $config]);

    return DB::connection($name);
}

beforeEach(function (): void {
    // D12: mod_* is PG-only (advisory-xact-lock, jsonb, CHECK). Reset to a
    // clean public schema so each test starts with no migration history.
    lifecyclePg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    lifecyclePg()->statement('CREATE SCHEMA public');
    DB::purge(LIFECYCLE_TEST_CONNECTION);

    config(['corex.modules.connection' => LIFECYCLE_TEST_CONNECTION]);
    app()->forgetInstance(ModulesOptions::class);
    $this->app->register(ModulesServiceProvider::class);

    $this->app->instance(ModuleDiscovery::class, new ModuleDiscovery(fallbackModules: [
        StubBaseModule::class,
        StubDependentModule::class,
        StubFailingModule::class,
        StubFkModule::class,
    ]));

    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], LIFECYCLE_TEST_CONNECTION);

    $this->registryPath = sys_get_temp_dir().'/corex_modules_lifecycle_test_'.bin2hex(random_bytes(4)).'.php';
    CompiledModuleRegistry::writeAtomic($this->registryPath, [
        new CompiledModule('corex/stub-base', 'Stub Base', 'sba', [], [], []),
        new CompiledModule('corex/stub-dependent', 'Stub Dependent', 'sbd', [
            ['kind' => 'module', 'package' => 'corex/stub-base', 'constraint' => '*'],
        ], [], []),
        new CompiledModule('corex/stub-failing', 'Stub Failing', 'sbf', [], [], []),
        new CompiledModule('corex/stub-fk', 'Stub FK', 'stf', [
            ['kind' => 'module', 'package' => 'corex/stub-base', 'constraint' => '*'],
        ], [], []),
    ]);

    $this->app->instance(ModuleRegistry::class, lifecycleModuleRegistry($this->registryPath));
});

/**
 * A CompiledModuleRegistry pinned to the pg test connection (the seam a P2
 * tenancy resolver populates per account), with a real D39 epoch resolver
 * reading the mod_lifecycle_log row count on that same connection. An
 * isolated cache can be injected to model a second, independent node/process.
 */
function lifecycleModuleRegistry(string $path, ?ModuleRegistryCache $cache = null): CompiledModuleRegistry
{
    return new CompiledModuleRegistry(
        cachePath: $path,
        stateReader: app()->make(DatabaseModuleStateReader::class, ['connection' => LIFECYCLE_TEST_CONNECTION]),
        cache: $cache ?? new RepositoryModuleRegistryCache(
            Cache::store(),
            ModulesOptions::fromArray(['cache' => ['ttl' => 3600, 'prefix' => 'corex.modules']]),
        ),
        versionResolver: static fn (TenantContext $ctx): int => (int) lifecyclePg()->table('mod_lifecycle_log')->count(),
    );
}

function lifecycleCtx(string $accountId = 'acc-1'): TenantContext
{
    return new TenantContext(new AccountRef(id: $accountId, slug: $accountId, status: 'active', features: [], limits: []));
}

/** An isolated in-memory cache — models a separate node whose store the disabling process cannot reach. */
function lifecycleIsolatedCache(): RepositoryModuleRegistryCache
{
    return new RepositoryModuleRegistryCache(
        new CacheRepository(new ArrayStore),
        ModulesOptions::fromArray(['cache' => ['ttl' => 3600, 'prefix' => 'corex.modules']]),
    );
}

function lifecycleHasColumn(string $table, string $column): bool
{
    return (int) lifecyclePg()->selectOne(
        'select count(*) as c from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
        [$table, $column],
    )->c > 0;
}

function lifecycleHasIndex(string $index): bool
{
    return (int) lifecyclePg()->selectOne(
        'select count(*) as c from pg_indexes where schemaname = current_schema() and indexname = ?',
        [$index],
    )->c > 0;
}

afterEach(function (): void {
    if (file_exists($this->registryPath)) {
        unlink($this->registryPath);
    }
});

it('enables a module from available: migrations run, mod_records + mod_modules populated, event dispatched (AC-5)', function (): void {
    Event::fake([ModuleEnabled::class]);

    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])
        ->enable('corex/stub-base');

    $row = lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->first();
    expect($row)->not->toBeNull()
        ->and($row->state)->toBe(ModuleState::Enabled->value)
        ->and($row->edition)->toBe('standard')
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue()
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_notes'))->toBeTrue();

    // sba_widgets/sba_notes were auto-claimed by the migration wrapper (no
    // hand-written claim() in the migrations); `greeting` is claimed by the hook.
    $claimed = lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->pluck('reference')->all();
    expect($claimed)->toEqualCanonicalizing(['sba_widgets', 'sba_notes', 'greeting']);

    $log = lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-base')->where('operation', 'enable')->first();
    expect($log->status)->toBe('success')
        ->and($log->from_state)->toBe(ModuleState::Available->value)
        ->and($log->to_state)->toBe(ModuleState::Enabled->value);

    Event::assertDispatched(ModuleEnabled::class, fn (ModuleEnabled $event): bool => $event->module === 'corex/stub-base');
});

it('claims and cleans up through the wrapped dispatcher when events are faked', function (): void {
    // Event::fake() forwards listen() but ignores forget(): claim listeners must live on the real dispatcher.
    $fake = Event::fake([ModuleEnabled::class]);
    $before = count($fake->dispatcher->getRawListeners()[MigrationStarted::class] ?? []);

    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])->enable('corex/stub-base');

    expect(lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->pluck('reference')->all())
        ->toContain('sba_widgets')
        ->and(count($fake->dispatcher->getRawListeners()[MigrationStarted::class] ?? []))->toBe($before);
});

it('rejects enable when a dependency is not enabled, without touching the database (AC-6)', function (): void {
    $lifecycle = lifecycleFor();

    expect(fn () => $lifecycle->enable('corex/stub-dependent'))->toThrow(MissingDependencyException::class);

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-dependent')->exists())->toBeFalse();
});

it('cascade auto-enables the requires chain', function (): void {
    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])
        ->enable('corex/stub-dependent', cascade: true);

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Enabled->value)
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-dependent')->value('state'))->toBe(ModuleState::Enabled->value);
});

it('rolls the whole transition back and throws when migration K fails — all-or-nothing, a fixed retry starts clean (AC-7, D40)', function (): void {
    // D40: a failing migration aborts EVERYTHING — migration 1 (sbf_ok) is
    // rolled back too, no mod_modules row, and the caller gets an exception
    // (the pre-D40 code silently committed 1..K-1 and returned void).
    expect(fn () => lifecycleFor(migrationPaths: ['corex/stub-failing' => lifecycleStubsPath('failing')])->enable('corex/stub-failing'))
        ->toThrow(ModuleEnableFailedException::class);

    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sbf_ok'))->toBeFalse()
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-failing')->exists())->toBeFalse()
        ->and(lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-failing')->exists())->toBeFalse();

    // Deploy "fixes" the module (boom.php removed) — a retry starts from a clean
    // state (reformulated AC-7) and completes.
    lifecycleFor(migrationPaths: ['corex/stub-failing' => lifecycleStubsPath('failing-fixed')])
        ->enable('corex/stub-failing');

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-failing')->value('state'))->toBe(ModuleState::Enabled->value)
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sbf_ok'))->toBeTrue();
});

it('never touches DATA on disable and blocks when a dependent module is enabled (AC-8)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->enable('corex/stub-dependent');

    // REAL rows in the module's data table — the snapshot is taken FROM the data
    // (A14/A15: the old test snapshotted mod_records with empty tables and would
    // not have caught a DELETE in the disable path).
    lifecyclePg()->table('sba_widgets')->insert([['name' => 'alpha'], ['name' => 'beta']]);
    $before = lifecyclePg()->table('sba_widgets')->orderBy('id')->get();

    expect(fn () => $lifecycle->disable('corex/stub-base', DisableReason::Manual))
        ->toThrow(HasEnabledDependentsException::class);

    $lifecycle->disable('corex/stub-dependent', DisableReason::Manual);
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);

    $after = lifecyclePg()->table('sba_widgets')->orderBy('id')->get();

    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue()
        ->and($after->toArray())->toEqual($before->toArray())
        ->and($after)->toHaveCount(2)
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Disabled->value)
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('disable_reason'))->toBe(DisableReason::Manual->value);
});

it('rejects uninstall directly from enabled — two-step safety (B-10 §5.1)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');

    expect(fn () => $lifecycle->uninstall('corex/stub-base', keepData: true))->toThrow(LogicException::class);
});

it('archives on uninstall(keepData: true) and re-enables cleanly (AC-9)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);
    $lifecycle->uninstall('corex/stub-base', keepData: true);

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Archived->value)
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue();

    $lifecycle->enable('corex/stub-base');

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Enabled->value);
});

it('purges every owned object in reverse creation order — FK child before parent, and column/index without dropping the borrowed table (AC-10, D31, D41)', function (): void {
    // stub-fk owns stf_parents ← stf_children (real FK, parent created first)
    // AND a column+index on the BASE module\'s sba_widgets. Purge must drop them
    // all: children before parents (FK-safe, Blocker A3), and the borrowed
    // column/index without dropping sba_widgets (D41 — all claim types).
    $lifecycle = lifecycleFor(migrationPaths: [
        'corex/stub-base' => lifecycleStubsPath('base'),
        'corex/stub-fk' => lifecycleStubsPath('fk'),
    ]);
    $lifecycle->enable('corex/stub-fk', cascade: true);

    // Populated tables — purge must drop them regardless of contents. stf_extra
    // is the borrowed column on sba_widgets, not on stf_parents.
    lifecyclePg()->table('stf_parents')->insert(['id' => 1, 'name' => 'p']);
    lifecyclePg()->table('stf_children')->insert(['id' => 1, 'parent_id' => 1, 'label' => 'c']);
    lifecyclePg()->table('sba_widgets')->insert(['id' => 1, 'name' => 'w', 'stf_extra' => 'x']);

    expect(lifecycleHasColumn('sba_widgets', 'stf_extra'))->toBeTrue()
        ->and(lifecycleHasIndex('stf_widgets_extra_ix'))->toBeTrue();

    $lifecycle->disable('corex/stub-fk', DisableReason::Manual);

    // Harden the fixture so ONLY `seq DESC` yields the FK-safe drop order (P1.18,
    // P3.7): collapse every stub-fk claim onto the SAME created_at, leaving the
    // bigserial `seq` as the only monotonic tiebreak. The real code orders by
    // `seq DESC` (child before parent → FK-safe), unaffected. A mutant ordering
    // by `created_at DESC, reference DESC` can no longer lean on created_at, so
    // it falls to `reference DESC` — which sorts 'stf_parents' before
    // 'stf_children' → drops the parent while its FK child still references it →
    // Postgres 2BP01 → the mutation reddens. Without this, parent/child were
    // claimed a wall-clock tick apart and `created_at DESC` coincided with
    // `seq DESC`, letting the mutant survive.
    lifecyclePg()->table('mod_records')
        ->where('module_name', 'corex/stub-fk')
        ->update(['created_at' => '2026-07-15 12:00:00+00']);

    // The dispatcher is injected into the lifecycle: build the one under test after faking events.
    Event::fake();
    $lifecycle = lifecycleFor(migrationPaths: [
        'corex/stub-base' => lifecycleStubsPath('base'),
        'corex/stub-fk' => lifecycleStubsPath('fk'),
    ]);
    $lifecycle->uninstall('corex/stub-fk', keepData: false);

    // Zero orphaned objects of the module (D41): tables, borrowed column, index.
    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('stf_children'))->toBeFalse()
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('stf_parents'))->toBeFalse()
        ->and(lifecycleHasColumn('sba_widgets', 'stf_extra'))->toBeFalse()
        ->and(lifecycleHasIndex('stf_widgets_extra_ix'))->toBeFalse()
        // The borrowed table itself survives — only stub-fk's additions went.
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue()
        ->and(lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-fk')->exists())->toBeFalse()
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-fk')->value('state'))->toBe(ModuleState::Purged->value);

    $log = lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-fk')->where('operation', 'purge')->first();
    expect(json_decode((string) $log->details, true)['dropped_tables'])->toBe(['stf_children', 'stf_parents']);

    Event::assertDispatched(ModulePurged::class, fn (ModulePurged $event): bool => $event->module === 'corex/stub-fk');
});

it('serializes a concurrent enable through pg_advisory_xact_lock — a second holder blocks it (AC-11)', function (): void {
    // A separate session holds the module\'s advisory xact lock inside an open
    // transaction; a probe on yet another session sees it locked.
    $holder = lifecycleSession('pgsql_holder');
    $holder->beginTransaction();
    expect($holder->selectOne('select pg_try_advisory_xact_lock(hashtext(?)) as ok', ['corex/stub-base'])->ok)->toBeTrue();

    $probe = lifecycleSession('pgsql_probe');
    expect($probe->selectOne('select pg_try_advisory_xact_lock(hashtext(?)) as ok', ['corex/stub-base'])->ok)->toBeFalse();

    // enable() must WAIT on that lock: with a short lock_timeout it aborts
    // instead of proceeding — proof it takes the lock (remove lock() from
    // enable() and this enable runs to completion, A55/A56).
    lifecyclePg()->statement("set lock_timeout = '500ms'");

    expect(fn () => lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])->enable('corex/stub-base'))
        ->toThrow(QueryException::class);

    $holder->rollBack();

    // The blocked enable rolled back entirely (D40): no module row.
    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->exists())->toBeFalse();

    lifecyclePg()->statement('set lock_timeout = 0');
});

it('records every transition in mod_lifecycle_log (AC-13)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);
    $lifecycle->uninstall('corex/stub-base', keepData: true);

    // `id` (UUIDv7, ADR-003) is the tiebreak: it's time-sortable and generated
    // immediately before each insert, so it orders correctly even when two
    // entries tie on created_at's wall-clock resolution.
    $operations = lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-base')->oldest()->orderBy('id')->pluck('operation')->all();
    expect($operations)->toBe(['enable', 'disable', 'archive']);
});

it('writes the lifecycle-log actor from CurrentActor, not a hardcoded value (A54)', function (): void {
    // Unauthenticated: CurrentActor reports `system`, null id.
    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])->enable('corex/stub-base');

    $systemRow = lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-base')->where('operation', 'enable')->first();
    expect($systemRow->actor_type)->toBe('system')
        ->and($systemRow->actor_id)->toBeNull();

    // Authenticated: the actor follows CurrentActor to `user` + the identifier
    // (a uuid — mod_lifecycle_log.actor_id) — proof the value is sourced, not
    // the pre-A54 hardcoded 'system'.
    $userId = '019f61b4-0000-7000-8000-000000000042';
    Auth::setUser(new GenericUser(['id' => $userId]));

    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])->disable('corex/stub-base', DisableReason::Manual);

    $userRow = lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-base')->where('operation', 'disable')->first();
    expect($userRow->actor_type)->toBe('user')
        ->and($userRow->actor_id)->toBe($userId);
});

it('rejects enabling a purged module — purged is terminal (A11/A12, FSM gate)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);
    $lifecycle->uninstall('corex/stub-base', keepData: false);

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Purged->value);

    // A direct enable on a purged module would flip the row to enabled over
    // dropped tables (its migrations are still logged as ran) — rejected.
    expect(fn () => $lifecycle->enable('corex/stub-base'))->toThrow(InvalidStateTransitionException::class);

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Purged->value);
});

it('rejects upgrade unless the module is enabled (FSM gate, B-10 §5.5)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')], codeVersions: ['corex/stub-base' => '2.0.0']);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);

    expect(fn () => $lifecycle->upgrade('corex/stub-base'))->toThrow(InvalidStateTransitionException::class);
});

it('rolls the upgrade back and rethrows when a catch-up migration fails — schema_version stays put (P1.29 M-1, symmetric to enable D40)', function (): void {
    // Enable at the base schema, then run a catch-up upgrade whose SECOND
    // migration throws: the `failing` path's sbf_ok migration applies, then boom
    // aborts. Pre-P1.29 upgrade() committed `schema_version=end(ran)`, logged
    // 'failed' and returned WITHOUT rethrowing — a silently half-migrated module,
    // the exact silent-failure D40 closed for enable().
    lifecycleFor(
        migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')],
        codeVersions: ['corex/stub-base' => '1.0.0'],
    )->enable('corex/stub-base');

    $before = lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->first();

    expect(fn () => lifecycleFor(
        migrationPaths: ['corex/stub-base' => lifecycleStubsPath('failing')],
        codeVersions: ['corex/stub-base' => '2.0.0'],
    )->upgrade('corex/stub-base'))->toThrow(ModuleUpgradeFailedException::class);

    $after = lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->first();

    // All-or-nothing: the catch-up migration's table rolled back, the version /
    // schema_version bump did NOT commit, and no 'upgrade' log row survives.
    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sbf_ok'))->toBeFalse()
        ->and($after->version)->toBe($before->version)
        ->and($after->schema_version)->toBe($before->schema_version)
        ->and(lifecyclePg()->table('mod_lifecycle_log')
            ->where('module_name', 'corex/stub-base')->where('operation', 'upgrade')->exists())->toBeFalse();
});

it('corex:modules:repair returns a purged module to available, and a fresh enable re-installs it (repair, D40)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);
    $lifecycle->uninstall('corex/stub-base', keepData: false);

    expect(fn () => $lifecycle->enable('corex/stub-base'))->toThrow(InvalidStateTransitionException::class);

    // repair() reconciles + resets the terminal `purged` to `available`, and
    // forgets the module's migration history so the next enable re-runs the DDL.
    $lifecycle->repair('corex/stub-base');

    expect($lifecycle->status('corex/stub-base')->state)->toBe(ModuleState::Available)
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->exists())->toBeFalse();

    $lifecycle->enable('corex/stub-base');

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Enabled->value)
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue();
});

it('reconciles mod_records with the schema — drops a claim whose table vanished out of band (repair)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');

    // A table disappears out of band; its ownership claim is now stale.
    lifecyclePg()->statement('DROP TABLE sba_notes');
    expect(lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->where('reference', 'sba_notes')->exists())->toBeTrue();

    $lifecycle->repair('corex/stub-base');

    expect(lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->where('reference', 'sba_notes')->exists())->toBeFalse()
        // The surviving table's claim is untouched.
        ->and(lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->where('reference', 'sba_widgets')->exists())->toBeTrue();
});

it('runs the corex:modules:repair artisan command against the module connection', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);
    $lifecycle->uninstall('corex/stub-base', keepData: false);

    // The container-resolved lifecycle (config connection + wiring binding) is
    // what the command uses — prove the command path reaches the module DB.
    lifecycleFromContainer(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);

    $this->artisan('corex:modules:repair', ['module' => 'corex/stub-base'])->assertExitCode(0);

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->exists())->toBeFalse();
});

it('runs migrations resolved through the container, not a hand-built constructor (wiring from discovery)', function (): void {
    // No manual migrationPaths in a constructor — they come from the container
    // `corex.modules.wiring` binding (installed.json in prod, stub path here).
    lifecycleFromContainer(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])
        ->enable('corex/stub-base');

    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue()
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Enabled->value);
});

it('derives pendingUpgrade from codeVersion vs. the stored dbVersion, never stores it (AC-12, derived part)', function (): void {
    $status = lifecycleFor()->status('corex/stub-base');
    expect($status->state)->toBe(ModuleState::Available)
        ->and($status->dbVersion)->toBeNull()
        ->and($status->pendingUpgrade)->toBeFalse();

    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')], codeVersions: ['corex/stub-base' => '1.2.0'])
        ->enable('corex/stub-base');

    expect(lifecycleFor(codeVersions: ['corex/stub-base' => '1.2.0'])->status('corex/stub-base')->pendingUpgrade)->toBeFalse()
        ->and(lifecycleFor(codeVersions: ['corex/stub-base' => '1.3.0'])->status('corex/stub-base')->pendingUpgrade)->toBeTrue();

    lifecycleFor(codeVersions: ['corex/stub-base' => '1.3.0'])->upgrade('corex/stub-base');

    expect(lifecycleFor(codeVersions: ['corex/stub-base' => '1.3.0'])->status('corex/stub-base')->pendingUpgrade)->toBeFalse()
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('version'))->toBe('1.3.0');
});

it('makes an enabled module isActive immediately — epoch invalidation, no TTL wait (D39, test б)', function (): void {
    $registry = $this->app->make(ModuleRegistry::class);
    $ctx = lifecycleCtx();

    expect($registry->isActive('corex/stub-base', $ctx))->toBeFalse();

    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])->enable('corex/stub-base');

    // Warm read happened above (false, cached under the pre-enable epoch); the
    // enable bumped the epoch, so this read routes to a fresh key — no forget()
    // on the registry, no TTL wait.
    expect($registry->isActive('corex/stub-base', $ctx))->toBeTrue();
});

it('makes a disabled module isActive=false immediately — the billing-disable path (D39, test в)', function (): void {
    $registry = $this->app->make(ModuleRegistry::class);
    $ctx = lifecycleCtx();
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);

    $lifecycle->enable('corex/stub-base');
    expect($registry->isActive('corex/stub-base', $ctx))->toBeTrue(); // warms the enabled state

    $lifecycle->disable('corex/stub-base', DisableReason::Billing);

    // The most dangerous case: a billing-disabled module must drop off the
    // hot path at once, not stay "active" until the TTL lapses.
    expect($registry->isActive('corex/stub-base', $ctx))->toBeFalse();
});

it('propagates a disable to another node without a forget() there — shared-DB epoch (D39, test г)', function (): void {
    // Node B has its OWN cache store — the disabling process cannot reach it.
    // Only the shared-DB epoch makes B self-invalidate; a caller-local
    // forget() (the rejected D30 mechanism) would leave B stale (pre-mortem C2).
    $nodeB = lifecycleModuleRegistry($this->registryPath, lifecycleIsolatedCache());
    $ctx = lifecycleCtx();
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);

    $lifecycle->enable('corex/stub-base');
    expect($nodeB->isActive('corex/stub-base', $ctx))->toBeTrue(); // node B warms its own store

    $lifecycle->disable('corex/stub-base', DisableReason::Billing);

    // Node B never called forget(); it sees the change purely because the
    // epoch bumped in the shared DB and its cache key moved on.
    expect($nodeB->isActive('corex/stub-base', $ctx))->toBeFalse();
});

it('reads the module state from the injected connection, not the ambient default (C3 seam, test з)', function (): void {
    // The module system is bound to LIFECYCLE_TEST_CONNECTION (pgsql) — the
    // seam a P2 tenancy resolver fills per account. It must read THAT database,
    // not config('database.default') (sqlite here, where mod_modules/
    // mod_lifecycle_log do not even exist).
    expect(config('database.default'))->not->toBe(LIFECYCLE_TEST_CONNECTION);

    $registry = $this->app->make(ModuleRegistry::class);
    $ctx = lifecycleCtx();

    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])->enable('corex/stub-base');

    // The enabled state and the epoch both came from the pinned pg connection;
    // an ambient-default read would have thrown (no such table) or returned
    // empty. isActive=true proves the connection came from the seam.
    expect($registry->isActive('corex/stub-base', $ctx))->toBeTrue()
        ->and(lifecyclePg()->table('mod_lifecycle_log')->count())->toBeGreaterThan(0);
});

it('carries a UUIDv7 eventId, occurredAt, tenant and the canonical B-10 §3.1 name on every lifecycle event (P1.25, A68, test а)', function (): void {
    $tenant = resolve(TenantContextResolver::class)->current();

    $events = [
        new ModuleEnabled('corex/stub-base', 'standard', ['0001_x.php']),
        new ModuleDisabled('corex/stub-base', DisableReason::Manual),
        new ModuleArchived('corex/stub-base'),
        new ModulePurged('corex/stub-base', ['sba_widgets']),
        new ModuleUpgraded('corex/stub-base', '1.0.0', '1.1.0', ['0002_y.php']),
    ];
    $names = ['mod.module.enabled', 'mod.module.disabled', 'mod.module.archived', 'mod.module.purged', 'mod.module.upgraded'];

    foreach ($events as $i => $event) {
        expect($event)->toBeInstanceOf(DomainEvent::class)
            ->and($event::name())->toBe($names[$i])
            ->and($event->eventId)->toBeString()->not->toBeEmpty()
            ->and($event->occurredAt)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($event->tenant)->toEqual($tenant);
    }
});

it('never dispatches ModuleEnabled when the enabling transaction rolls back — afterCommit survives the DomainEvent retrofit (P1.25, test б)', function (): void {
    Event::fake();

    expect(fn () => lifecycleFor(migrationPaths: ['corex/stub-failing' => lifecycleStubsPath('failing')])->enable('corex/stub-failing'))
        ->toThrow(ModuleEnableFailedException::class);

    Event::assertNotDispatched(ModuleEnabled::class);
});

it('defines a manifest-declared feature flag in sys_feature_flags when its module is enabled (P1.25, A68, test г)', function (): void {
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], LIFECYCLE_TEST_CONNECTION);

    $this->app->instance(FeatureFlags::class, new DatabaseFeatureFlags(connection: LIFECYCLE_TEST_CONNECTION));

    $this->app->instance(ModuleDiscovery::class, new ModuleDiscovery(fallbackModules: [
        StubBaseModule::class,
        StubDependentModule::class,
        StubFailingModule::class,
        StubFkModule::class,
        StubFeatureFlagModule::class,
    ]));

    CompiledModuleRegistry::writeAtomic($this->registryPath, [
        new CompiledModule('corex/stub-base', 'Stub Base', 'sba', [], [], []),
        new CompiledModule('corex/stub-dependent', 'Stub Dependent', 'sbd', [
            ['kind' => 'module', 'package' => 'corex/stub-base', 'constraint' => '*'],
        ], [], []),
        new CompiledModule('corex/stub-failing', 'Stub Failing', 'sbf', [], [], []),
        new CompiledModule('corex/stub-fk', 'Stub FK', 'stf', [
            ['kind' => 'module', 'package' => 'corex/stub-base', 'constraint' => '*'],
        ], [], []),
        new CompiledModule('corex/stub-feature-flag', 'Stub Feature Flag', 'sff', [], [], []),
    ]);
    $this->app->instance(ModuleRegistry::class, lifecycleModuleRegistry($this->registryPath));

    lifecycleFor()->enable('corex/stub-feature-flag');

    $flag = lifecyclePg()->table('sys_feature_flags')->where('key', 'sff.beta_mode')->first();

    expect($flag)->not->toBeNull()
        ->and((bool) $flag->is_enabled)->toBeFalse()
        ->and($flag->module)->toBe('corex/stub-feature-flag');

    // Idempotent (P1.22): a second enable() of the SAME module is a lock-serialized
    // no-op (AC-11) that never reaches defineManifestFeatureFlags() again — no
    // duplicate/UNIQUE-violation row.
    lifecycleFor()->enable('corex/stub-feature-flag');

    expect(lifecyclePg()->table('sys_feature_flags')->where('key', 'sff.beta_mode')->count())->toBe(1);
});

it('resolves ModuleStateReader from the registered ModulesOptions connection, not the app default (D63/D65 Major)', function (): void {
    // mod_modules lives on the pg test connection; the app default (sqlite in
    // this suite) has no such table. A container-resolved ModuleStateReader must
    // read the CONFIGURED module connection — the bare class-string binding this
    // regression pins autowires connection=null and hits the app default, so the
    // enabled set reads empty (or throws) and every module reads inactive.
    lifecyclePg()->table('mod_modules')->insert([
        'id' => (string) Str::uuid7(),
        'name' => 'corex/stub-base',
        'state' => ModuleState::Enabled->value,
        'version' => '1.0.0',
        'edition' => 'standard',
    ]);

    expect(resolve(ModulesOptions::class)->connection)->toBe(LIFECYCLE_TEST_CONNECTION);
    app()->forgetInstance(ModuleStateReader::class);

    // NO manual `app()->make(DatabaseModuleStateReader::class, ['connection' => ...])` override —
    // resolve straight from the container, exactly as CompiledModuleRegistry does.
    $reader = resolve(ModuleStateReader::class);

    expect($reader->enabledFor(lifecycleCtx()))->toHaveKey('corex/stub-base');
});

it('enables a module using its registered set without an installed migration directory', function (): void {
    require_once __DIR__.'/../Fixtures/Schema/FixtureSchemaSets.php';
    $set = new SchemaSet(
        package: 'corex/stub-base', name: 'tenant', scope: SchemaScope::Tenant,
        path: lifecycleStubsPath('base'), version: 1, lifecycle: SchemaLifecycle::Module,
    );
    app()->instance(SchemaSets::class, new TaggedSchemaSets([
        new FixtureSchemaSets([$set]),
    ]));
    app()->forgetInstance('corex.modules.wiring');
    app()->forgetInstance(ModuleLifecycle::class);
    app()->forgetInstance(RecordsRegistrar::class);
    resolve(ModuleLifecycle::class)->enable('corex/stub-base');
    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue();
});
