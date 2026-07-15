<?php

declare(strict_types=1);

use CoreX\Tests\Stubs\StubCorexModule;
use Flex\Modules\Dep;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Exceptions\InvalidManifestException;
use Flex\Modules\Manifest;
use Flex\Modules\ModulesServiceProvider;
use Flex\Modules\SettingDefault;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->app->register(ModulesServiceProvider::class);
});

it('builds a fluent manifest with readable fields', function (): void {
    $manifest = Manifest::make('corex/stub-module')
        ->title('Stub Module')
        ->description('A stub for tests.')
        ->tablePrefix('stb')
        ->requires([Dep::module('corex/core')])
        ->editions(['standard' => [], 'pro' => ['advanced-reports']])
        ->settings([SettingDefault::make('greeting', 'hello')])
        ->listens(['App\\Events\\Foo' => ['App\\Listeners\\Bar']])
        ->onEnable('App\\Hooks\\EnableStub');

    expect($manifest->composerName)->toBe('corex/stub-module')
        ->and($manifest->title)->toBe('Stub Module')
        ->and($manifest->description)->toBe('A stub for tests.')
        ->and($manifest->tablePrefix)->toBe('stb')
        ->and($manifest->requires)->toHaveCount(1)
        ->and($manifest->requires[0]->target)->toBe('corex/core')
        ->and($manifest->editions)->toBe(['standard' => [], 'pro' => ['advanced-reports']])
        ->and($manifest->settings[0]->key)->toBe('greeting')
        ->and($manifest->listeners)->toBe(['App\\Events\\Foo' => ['App\\Listeners\\Bar']])
        ->and($manifest->onEnableHook)->toBe('App\\Hooks\\EnableStub');
});

it('rejects an invalid tablePrefix with a precise message', function (): void {
    expect(fn () => Manifest::make('corex/stub-module')->tablePrefix('TOOLONG'))
        ->toThrow(InvalidManifestException::class, 'table prefix [TOOLONG] is invalid');
});

it('rejects an invalid composer name', function (): void {
    expect(fn () => Manifest::make('Not A Valid Name'))
        ->toThrow(InvalidManifestException::class);
});

it('rejects an empty title', function (): void {
    expect(fn () => Manifest::make('corex/stub-module')->title(''))
        ->toThrow(InvalidManifestException::class);
});

it('discovers manifests from an injected fallback list', function (): void {
    $discovery = new ModuleDiscovery(fallbackModules: [StubCorexModule::class]);

    $manifests = $discovery->discover();

    expect($manifests)->toHaveCount(1)
        ->and($manifests[0])->toBeInstanceOf(Manifest::class)
        ->and($manifests[0]->composerName)->toBe('corex/stub-module')
        ->and($manifests[0]->tablePrefix)->toBe('stb');
});

it('rejects a discovered class that does not extend CorexModule', function (): void {
    $discovery = new ModuleDiscovery(fallbackModules: [stdClass::class]);

    expect(fn () => $discovery->discover())->toThrow(InvalidManifestException::class);
});

it('flex:modules:compile does not fail on testbench with no corex-module packages installed', function (): void {
    $exitCode = Artisan::call('flex:modules:compile');

    expect($exitCode)->toBe(0);
});

it('flex:modules:compile reports discovered manifests from the bound ModuleDiscovery', function (): void {
    $this->app->instance(
        ModuleDiscovery::class,
        new ModuleDiscovery(fallbackModules: [StubCorexModule::class]),
    );

    $exitCode = Artisan::call('flex:modules:compile');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Discovered 1 module manifest(s).');
});
