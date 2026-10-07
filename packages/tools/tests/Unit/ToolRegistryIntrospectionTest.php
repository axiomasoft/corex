<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use CoreX\CoreServiceProvider;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\RegistryIntrospection;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Testing\CatalogueModuleFixture;
use CoreX\Operations\Testing\StubOperationHandler;
use CoreX\Tools\ToolServiceProvider;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

it('projects tool identity without leaking its handler into introspection', function (): void {
    $this->app->register(ApiServiceProvider::class);
    $this->app->register(ToolServiceProvider::class);
    $this->app->register(CoreServiceProvider::class);
    $this->app->register(ModulesServiceProvider::class);
    $manifest = ModuleManifest::make('example/tools')->title('Tools')->tablePrefix('tls')->extends([
        Api::tool('demo.read', StubOperationHandler::class, 'demo.read', ['type' => 'object'], ['type' => 'object'], 'read', 'read-only'),
    ]);
    $compiled = resolve(RegistryCompiler::class)->compile([$manifest], installedPackages: []);
    $this->app->instance(ModuleRegistry::class, new CatalogueModuleFixture($compiled));

    $map = resolve(RegistryIntrospection::class)->extensionPoints();
    $encoded = json_encode($map->extensionPoints, JSON_THROW_ON_ERROR);

    $api = collect($map->extensionPoints)->firstWhere('name', 'api');

    expect($api['entryCount'])->toBe(1)
        ->and($api['owners'])->toBe(['example/tools' => 1])
        ->and($encoded)->not->toContain(StubOperationHandler::class)
        ->and($map->revision)->toBe($compiled->fingerprint);
});
