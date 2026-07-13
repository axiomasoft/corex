<?php

declare(strict_types=1);

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tests\Stubs\Lifecycle\StubBaseModule;
use CoreX\Tests\Stubs\Lifecycle\StubDependentModule;
use CoreX\Tests\Stubs\Lifecycle\StubFailingModule;
use Flex\Modules\Contracts\ModuleRegistry;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Enums\DisableReason;
use Flex\Modules\Enums\ModuleState;
use Flex\Modules\Events\ModuleEnabled;
use Flex\Modules\Events\ModulePurged;
use Flex\Modules\Exceptions\HasEnabledDependentsException;
use Flex\Modules\Exceptions\MissingDependencyException;
use Flex\Modules\Lifecycle\DatabaseModuleLifecycle;
use Flex\Modules\Lifecycle\DatabaseModuleStateReader;
use Flex\Modules\Lifecycle\DatabaseRecordsRegistrar;
use Flex\Modules\ModulesServiceProvider;
use Flex\Modules\Registry\CompiledModule;
use Flex\Modules\Registry\CompiledModuleRegistry;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

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
        registry: app(ModuleRegistry::class),
        discovery: app(ModuleDiscovery::class),
        records: new DatabaseRecordsRegistrar(connection: LIFECYCLE_TEST_CONNECTION),
        container: app(),
        tenantContextResolver: app(TenantContextResolver::class),
        codeVersions: $codeVersions,
        migrationPaths: $migrationPaths,
        connection: LIFECYCLE_TEST_CONNECTION,
    );
}

beforeEach(function (): void {
    // D12: mod_* is PG-only (advisory-xact-lock, jsonb, CHECK). Reset to a
    // clean public schema so each test starts with no migration history.
    lifecyclePg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    lifecyclePg()->statement('CREATE SCHEMA public');
    DB::purge(LIFECYCLE_TEST_CONNECTION);

    $this->app->register(ModulesServiceProvider::class);

    $this->app->instance(ModuleDiscovery::class, new ModuleDiscovery(fallbackModules: [
        StubBaseModule::class,
        StubDependentModule::class,
        StubFailingModule::class,
    ]));

    $this->artisan('migrate', [
        '--database' => LIFECYCLE_TEST_CONNECTION,
        '--path' => realpath(__DIR__.'/../../packages/modules/database/migrations'),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    $this->registryPath = sys_get_temp_dir().'/flex_modules_lifecycle_test_'.bin2hex(random_bytes(4)).'.php';
    CompiledModuleRegistry::writeAtomic($this->registryPath, [
        new CompiledModule('corex/stub-base', 'Stub Base', 'sba', [], [], []),
        new CompiledModule('corex/stub-dependent', 'Stub Dependent', 'sbd', [
            ['type' => 'module', 'target' => 'corex/stub-base', 'constraint' => '*'],
        ], [], []),
        new CompiledModule('corex/stub-failing', 'Stub Failing', 'sbf', [], [], []),
    ]);

    $this->app->instance(ModuleRegistry::class, new CompiledModuleRegistry(
        cachePath: $this->registryPath,
        stateReader: new DatabaseModuleStateReader(connection: LIFECYCLE_TEST_CONNECTION),
        cache: Cache::store(),
    ));
});

afterEach(function (): void {
    if (file_exists($this->registryPath)) {
        unlink($this->registryPath);
    }
});

it('enables a module from available: migrations run, mod_records + mod_modules populated, event dispatched (AC-5)', function (): void {
    Event::fake();

    lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')])
        ->enable('corex/stub-base');

    $row = lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->first();
    expect($row)->not->toBeNull()
        ->and($row->state)->toBe(ModuleState::Enabled->value)
        ->and($row->edition)->toBe('standard')
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue()
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_notes'))->toBeTrue();

    $claimed = lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->pluck('reference')->all();
    expect($claimed)->toEqualCanonicalizing(['sba_widgets', 'sba_notes', 'greeting']);

    $log = lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-base')->where('operation', 'enable')->first();
    expect($log->status)->toBe('success')
        ->and($log->from_state)->toBe(ModuleState::Available->value)
        ->and($log->to_state)->toBe(ModuleState::Enabled->value);

    Event::assertDispatched(ModuleEnabled::class, fn (ModuleEnabled $event): bool => $event->module === 'corex/stub-base');
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

it('keeps 1..K-1 applied on a migration K failure, leaves mod_modules untouched, and a fixed retry completes (AC-7)', function (): void {
    lifecycleFor(migrationPaths: ['corex/stub-failing' => lifecycleStubsPath('failing')])
        ->enable('corex/stub-failing');

    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sbf_ok'))->toBeTrue() // migration 1 committed
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-failing')->exists())->toBeFalse();

    $log = lifecyclePg()->table('mod_lifecycle_log')
        ->where('module_name', 'corex/stub-failing')->where('operation', 'enable')->where('status', 'failed')
        ->first();
    expect($log)->not->toBeNull()
        ->and(json_decode((string) $log->details, true)['error'])->toContain('boom');

    // Deploy "fixes" module K (boom.php removed) — retry with the corrected
    // path completes: migration 1 is already tracked as ran, so only the
    // hook + row insert happen this time.
    lifecycleFor(migrationPaths: ['corex/stub-failing' => lifecycleStubsPath('failing-fixed')])
        ->enable('corex/stub-failing');

    expect(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-failing')->value('state'))->toBe(ModuleState::Enabled->value);
});

it('never touches data on disable and blocks when a dependent module is enabled (AC-8)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->enable('corex/stub-dependent');

    $before = lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->orderBy('reference')->get();

    expect(fn () => $lifecycle->disable('corex/stub-base', DisableReason::Manual))
        ->toThrow(HasEnabledDependentsException::class);

    $lifecycle->disable('corex/stub-dependent', DisableReason::Manual);
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);

    $after = lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->orderBy('reference')->get();

    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeTrue()
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_notes'))->toBeTrue()
        ->and($after->toArray())->toEqual($before->toArray())
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

it('purges by dropping tables in reverse mod_records order, clears the registry, dispatches ModulePurged (AC-10)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);

    Event::fake();
    $lifecycle->uninstall('corex/stub-base', keepData: false);

    expect(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_widgets'))->toBeFalse()
        ->and(Schema::connection(LIFECYCLE_TEST_CONNECTION)->hasTable('sba_notes'))->toBeFalse()
        ->and(lifecyclePg()->table('mod_records')->where('module_name', 'corex/stub-base')->exists())->toBeFalse()
        ->and(lifecyclePg()->table('mod_modules')->where('name', 'corex/stub-base')->value('state'))->toBe(ModuleState::Purged->value);

    $log = lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-base')->where('operation', 'purge')->first();
    // Both migrations run inside the same enable() transaction and can land
    // on the same wall-clock microsecond — mod_records has no surrogate id,
    // so the drop order ties back to the documented `reference DESC`
    // tiebreak (DatabaseModuleLifecycle::purge()), not raw claim order.
    expect(json_decode((string) $log->details, true)['dropped_tables'])->toBe(['sba_widgets', 'sba_notes']);

    Event::assertDispatched(ModulePurged::class, fn (ModulePurged $event): bool => $event->module === 'corex/stub-base');
});

it('makes a second enable of an already-enabled module a no-op (AC-11)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);

    $lifecycle->enable('corex/stub-base');
    $lifecycle->enable('corex/stub-base');

    $successes = lifecyclePg()->table('mod_lifecycle_log')
        ->where('module_name', 'corex/stub-base')->where('operation', 'enable')->where('status', 'success')
        ->count();
    expect($successes)->toBe(1);
});

it('records every transition in mod_lifecycle_log (AC-13)', function (): void {
    $lifecycle = lifecycleFor(migrationPaths: ['corex/stub-base' => lifecycleStubsPath('base')]);
    $lifecycle->enable('corex/stub-base');
    $lifecycle->disable('corex/stub-base', DisableReason::Manual);
    $lifecycle->uninstall('corex/stub-base', keepData: true);

    // `id` (UUIDv7, ADR-003) is the tiebreak: it's time-sortable and generated
    // immediately before each insert, so it orders correctly even when two
    // entries tie on created_at's wall-clock resolution.
    $operations = lifecyclePg()->table('mod_lifecycle_log')->where('module_name', 'corex/stub-base')->orderBy('created_at')->orderBy('id')->pluck('operation')->all();
    expect($operations)->toBe(['enable', 'disable', 'archive']);
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
