<?php

declare(strict_types=1);

use CoreX\Modules\Definition\ModuleDependency;
use CoreX\Modules\Definition\ModuleManifest;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Exceptions\InvalidManifestException;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Modules\Settings\DefaultSettingDefinition;
use CoreX\Tests\Stubs\StubModuleDefinition;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->app->register(ModulesServiceProvider::class);
});

it('builds a fluent manifest with readable fields', function (): void {
    $manifest = ModuleManifest::make('corex/stub-module')
        ->title('Stub Module')
        ->description('A stub for tests.')
        ->tablePrefix('stb')
        ->requires([ModuleDependency::module('axiomasoft/corex')])
        ->editions(['standard' => [], 'pro' => ['advanced-reports']])
        ->settings([DefaultSettingDefinition::make('greeting', 'hello')])
        ->listens(['App\\Events\\Foo' => ['App\\Listeners\\Bar']])
        ->onEnable('App\\Hooks\\EnableStub');

    expect($manifest->composerName)->toBe('corex/stub-module')
        ->and($manifest->title)->toBe('Stub Module')
        ->and($manifest->description)->toBe('A stub for tests.')
        ->and($manifest->tablePrefix)->toBe('stb')
        ->and($manifest->requires)->toHaveCount(1)
        ->and($manifest->requires[0]->kind)->toBe('module')
        ->and($manifest->requires[0]->package)->toBe('axiomasoft/corex')
        ->and($manifest->editions)->toBe(['standard' => [], 'pro' => ['advanced-reports']])
        ->and($manifest->settings[0]->key)->toBe('greeting')
        ->and($manifest->listeners)->toBe(['App\\Events\\Foo' => ['App\\Listeners\\Bar']])
        ->and($manifest->onEnableHook)->toBe('App\\Hooks\\EnableStub');
});

it('rejects an invalid tablePrefix with a precise message', function (): void {
    expect(fn () => ModuleManifest::make('corex/stub-module')->tablePrefix('TOOLONG'))
        ->toThrow(InvalidManifestException::class, 'table prefix [TOOLONG] is invalid');
});

it('rejects an invalid composer name', function (): void {
    expect(fn () => ModuleManifest::make('Not A Valid Name'))
        ->toThrow(InvalidManifestException::class);
});

it('rejects an empty title', function (): void {
    expect(fn () => ModuleManifest::make('corex/stub-module')->title(''))
        ->toThrow(InvalidManifestException::class);
});

it('discovers manifests from an injected fallback list', function (): void {
    $discovery = new ModuleDiscovery(fallbackModules: [StubModuleDefinition::class]);

    $manifests = $discovery->discover();

    expect($manifests)->toHaveCount(1)
        ->and($manifests[0])->toBeInstanceOf(ModuleManifest::class)
        ->and($manifests[0]->composerName)->toBe('corex/stub-module')
        ->and($manifests[0]->tablePrefix)->toBe('stb');
});

it('rejects a discovered class that does not extend ModuleDefinition', function (): void {
    $discovery = new ModuleDiscovery(fallbackModules: [stdClass::class]);

    expect(fn () => $discovery->discover())->toThrow(InvalidManifestException::class);
});

it('corex:modules:compile does not fail when discovery reports no CoreX module packages', function (): void {
    $installedJson = tempnam(sys_get_temp_dir(), 'corex-modules-');
    file_put_contents($installedJson, '{"packages":[]}');
    $this->app->instance(ModuleDiscovery::class, new ModuleDiscovery(installedJsonPath: $installedJson));

    try {
        $exitCode = Artisan::call('corex:modules:compile');
    } finally {
        unlink($installedJson);
    }

    expect($exitCode)->toBe(0);
});

it('corex:modules:compile reports discovered manifests from the bound ModuleDiscovery', function (): void {
    $this->app->instance(
        ModuleDiscovery::class,
        new ModuleDiscovery(fallbackModules: [StubModuleDefinition::class]),
    );

    $exitCode = Artisan::call('corex:modules:compile');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Discovered 1 module manifest(s).');
});
