<?php

declare(strict_types=1);

use CoreX\Contracts\FeatureFlags;
use CoreX\Features\DatabaseFeatureFlags;
use CoreX\Modules\Contracts\ModuleActivationGate;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Enums\DisableReason;
use CoreX\Modules\Events\ModuleEnabled;
use CoreX\Modules\Exceptions\ModuleEnableFailedException;
use CoreX\Modules\GateDecision;
use CoreX\Modules\Lifecycle\DatabaseModuleLifecycle;
use CoreX\Modules\Lifecycle\DatabaseRecordsRegistrar;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Modules\Registry\CompiledModule;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tests\Stubs\Lifecycle\StubBaseModule;
use CoreX\Tests\Stubs\Lifecycle\StubFailingModule;
use CoreX\Tests\Stubs\Lifecycle\StubFeatureFlagModule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses()->group('pg');

beforeEach(function (): void {
    config(['database.default' => 'pgsql', 'corex-modules.connection' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->app->register(ModulesServiceProvider::class);
    foreach ([dirname(__DIR__, 2).'/database/migrations', dirname(__DIR__, 3).'/core/database/migrations/pg'] as $path) {
        $this->artisan('migrate', ['--path' => realpath($path), '--realpath' => true, '--force' => true])->assertSuccessful();
    }
    $discovery = new ModuleDiscovery(fallbackModules: [StubBaseModule::class, StubFailingModule::class, StubFeatureFlagModule::class]);
    $registry = Mockery::mock(ModuleRegistry::class);
    foreach ($discovery->discover() as $manifest) {
        $registry->shouldReceive('get')->with($manifest->composerName)->andReturn(CompiledModule::fromManifest($manifest));
    }
    $this->app->instance(ModuleRegistry::class, $registry);
    $this->app->instance(FeatureFlags::class, new DatabaseFeatureFlags('pgsql'));
    $this->lifecycle = new DatabaseModuleLifecycle(
        registry: $registry, discovery: $discovery, records: new DatabaseRecordsRegistrar('pgsql'),
        container: app(), tenantContextResolver: app(TenantContextResolver::class),
        migrationPaths: [
            'corex/stub-base' => dirname(__DIR__, 4).'/tests/Stubs/Lifecycle/migrations/base',
            'corex/stub-failing' => dirname(__DIR__, 4).'/tests/Stubs/Lifecycle/migrations/failing',
        ], connection: 'pgsql',
    );
});

it('honors a denying activation gate before migrations and hooks', function (): void {
    $gate = Mockery::mock(ModuleActivationGate::class);
    $gate->shouldReceive('check')->once()->andReturn(GateDecision::deny('host policy'));
    app()->instance(ModuleActivationGate::class, $gate);
    expect(fn () => $this->lifecycle->enable('corex/stub-base'))->toThrow(AuthorizationException::class, 'host policy');
    expect(DB::table('mod_modules')->count())->toBe(0)
        ->and(Schema::hasTable('sba_widgets'))->toBeFalse();
});

it('serializes different module transitions on the dependency graph lock', function (): void {
    config(['database.connections.module_race' => config('database.connections.pgsql')]);
    DB::connection('module_race')->beginTransaction();
    DB::connection('module_race')->statement("SET LOCAL lock_timeout = '50ms'");
    $other = new DatabaseModuleLifecycle(
        registry: app(ModuleRegistry::class), discovery: new ModuleDiscovery(fallbackModules: [StubFeatureFlagModule::class]),
        records: new DatabaseRecordsRegistrar('module_race'), container: app(),
        tenantContextResolver: app(TenantContextResolver::class), connection: 'module_race',
    );
    $observed = false;
    DB::listen(function ($query) use (&$observed, $other): void {
        if ($observed || $query->connectionName !== 'pgsql' || ! in_array('corex:module-lifecycle-graph', $query->bindings, true)) {
            return;
        }
        $observed = true;

        try {
            $other->enable('corex/stub-feature-flag');
            test()->fail('Different modules mutated the graph concurrently.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[0])->toBe('55P03');
        }
    });

    try {
        $this->lifecycle->enable('corex/stub-base');
        expect($observed)->toBeTrue();
        $other->enable('corex/stub-feature-flag');
        DB::connection('module_race')->commit();
        expect(DB::table('mod_modules')->count())->toBe(2);
    } finally {
        if (DB::connection('module_race')->transactionLevel() > 0) {
            DB::connection('module_race')->rollBack();
        }
        DB::purge('module_race');
    }
});

it('preserves application migration listeners on success and failure', function (): void {
    $started = $ended = 0;
    app('events')->listen(MigrationStarted::class, function () use (&$started): void {
        $started++;
    });
    app('events')->listen(MigrationEnded::class, function () use (&$ended): void {
        $ended++;
    });
    $this->lifecycle->enable('corex/stub-base');
    expect($started)->toBeGreaterThan(0)->and($ended)->toBeGreaterThan(0);
    expect(fn () => $this->lifecycle->enable('corex/stub-failing'))->toThrow(ModuleEnableFailedException::class);
    $before = [$started, $ended];
    $migration = new class extends Migration {};
    app('events')->dispatch(new MigrationStarted($migration, 'up'));
    app('events')->dispatch(new MigrationEnded($migration, 'up'));
    expect([$started, $ended])->toBe([$before[0] + 1, $before[1] + 1]);
});

it('makes flags visible to ModuleEnabled and rolls back a failed definition', function (): void {
    $flags = Mockery::mock(FeatureFlags::class);
    $flags->shouldReceive('define')->once()->andThrow(new RuntimeException('definition failed'));
    app()->instance(FeatureFlags::class, $flags);
    expect(fn () => $this->lifecycle->enable('corex/stub-feature-flag'))->toThrow(RuntimeException::class, 'definition failed');
    expect(DB::table('mod_modules')->count())->toBe(0);
    app()->instance(FeatureFlags::class, new DatabaseFeatureFlags('pgsql'));
    $seen = false;
    app('events')->listen(ModuleEnabled::class, function () use (&$seen): void {
        $seen = DB::table('sys_feature_flags')->where('key', 'sff.beta_mode')->exists();
    });
    $this->lifecycle->enable('corex/stub-feature-flag');
    expect($seen)->toBeTrue();
    $this->lifecycle->disable('corex/stub-feature-flag', DisableReason::Manual);
    $this->lifecycle->enable('corex/stub-feature-flag');
    expect(DB::table('sys_feature_flags')->where('key', 'sff.beta_mode')->count())->toBe(1);
});
