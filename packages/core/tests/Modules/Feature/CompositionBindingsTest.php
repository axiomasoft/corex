<?php

declare(strict_types=1);

use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleActivationGate;
use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistryCache;
use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Contracts\RecordsRegistrar;
use CoreX\Modules\Contracts\RegistryIntrospection;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Gate\AllowAllActivationGate;
use CoreX\Modules\Internal\ReadRegistryIntrospection;
use CoreX\Modules\Laravel\LifecycleRegistrar;
use CoreX\Modules\Laravel\ModulesBootRegistrar;
use CoreX\Modules\Laravel\RegistryRegistrar;
use CoreX\Modules\Laravel\SettingsRegistrar;
use CoreX\Modules\Lifecycle\DatabaseModuleLifecycle;
use CoreX\Modules\Lifecycle\DatabaseModuleStateReader;
use CoreX\Modules\Lifecycle\DatabaseRecordsRegistrar;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\CompiledModuleRegistry;
use CoreX\Modules\Registry\RepositoryModuleRegistryCache;
use CoreX\Modules\Settings\CompiledSettingDefaultsProvider;
use CoreX\Modules\Support\ModulesOptions;
use CoreX\Settings\Contracts\SettingDefaultsProvider;
use Illuminate\Foundation\Application;

beforeEach(function (): void {
    $this->app->register(ModulesServiceProvider::class);
});

it('keeps Registry register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    assertSingletonBinding($app, ArrayEntityRegistry::class, ArrayEntityRegistry::class);
    assertSingletonBinding($app, EntityRegistry::class, ArrayEntityRegistry::class);
    assertSingletonBinding($app, ModuleDiscovery::class, ModuleDiscovery::class);
    assertSingletonBinding($app, ModuleActivationGate::class, AllowAllActivationGate::class);
    assertSingletonBinding($app, ModuleRegistryCache::class, RepositoryModuleRegistryCache::class);
    assertSingletonBinding($app, ModuleRegistry::class, CompiledModuleRegistry::class);
    assertSingletonBinding($app, RegistryIntrospection::class, ReadRegistryIntrospection::class);
});

it('keeps Lifecycle register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    assertSingletonBinding($app, ModuleStateReader::class, DatabaseModuleStateReader::class);
    assertSingletonBinding($app, RecordsRegistrar::class, DatabaseRecordsRegistrar::class);
    assertSingletonBinding($app, ModuleLifecycle::class, DatabaseModuleLifecycle::class);

    expect($app->getBindings())->toHaveKey('corex.modules.wiring');
    expect($app->isShared('corex.modules.wiring'))->toBeTrue();
    $first = $app->make('corex.modules.wiring');
    expect($first)->toBeArray()
        ->and($first)->toHaveKeys(['codeVersions', 'migrationPaths']);
    expect($app->make('corex.modules.wiring'))->toBe($first);
    $app->forgetScopedInstances();
    expect($app->make('corex.modules.wiring'))->toBe($first);
});

it('keeps Settings register bindings on the measured key, concrete and lifetime', function (): void {
    assertSingletonBinding(
        $this->app,
        SettingDefaultsProvider::class,
        CompiledSettingDefaultsProvider::class,
    );
});

it('resolves ModulesOptions from the composition instance', function (): void {
    $app = $this->app;
    expect($app->bound(ModulesOptions::class))->toBeTrue();
    $first = $app->make(ModulesOptions::class);
    expect($first)->toBeInstanceOf(ModulesOptions::class)
        ->and($app->make(ModulesOptions::class))->toBe($first);
});

it('reads module options from the nested kernel configuration', function (): void {
    config()->set('corex.modules.connection', 'module-test');
    config()->set('corex.modules.cache', ['store' => 'array', 'ttl' => 42, 'prefix' => 'module-test']);
    $this->app->register(ModulesServiceProvider::class, force: true);

    $options = $this->app->make(ModulesOptions::class);
    expect($options->connection)->toBe('module-test')
        ->and($options->cacheStore)->toBe('array')
        ->and($options->cacheTtl)->toBe(42)
        ->and($options->cachePrefix)->toBe('module-test');
});

it('registers composition modules through PackageRegistrar', function (): void {
    $registrars = [
        new RegistryRegistrar,
        new LifecycleRegistrar,
        new SettingsRegistrar,
        new ModulesBootRegistrar,
    ];

    foreach ($registrars as $registrar) {
        expect($registrar)->toBeInstanceOf(PackageRegistrar::class);
        $registrar->boot($this->app);
    }
});

function assertSingletonBinding(
    Application $app,
    string $abstract,
    string $expectedConcrete,
): void {
    expect($app->getBindings())->toHaveKey($abstract);
    expect($app->isShared($abstract))->toBeTrue();
    $first = $app->make($abstract);
    $second = $app->make($abstract);
    expect($first)->toBeInstanceOf($expectedConcrete);
    expect($first)->toBe($second);
    $app->forgetScopedInstances();
    expect($app->make($abstract))->toBe($first);
}
