<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use CoreX\Operations\Testing\CatalogueModuleFixture;
use CoreX\Operations\Testing\StubOperationHandler;
use CoreX\Tools\Tests\Support\BootsToolStack;
use Orchestra\Testbench\TestCase;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Contracts\Registry\RegistryIntrospection;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

uses(TestCase::class, BootsToolStack::class);

it('projects tool identity without leaking its handler into introspection', function (): void {
    $manifest = ModuleManifest::make('example/tools')->title('Tools')->tablePrefix('tls')->extends([
        Api::tool('demo.read', StubOperationHandler::class, 'demo.read', ['type' => 'object'], ['type' => 'object'], 'read', 'read-only'),
    ]);
    $compiled = resolve(ModuleCompilation::class)->compile([$manifest], installedPackages: []);
    $this->app->instance(ModuleRegistry::class, new CatalogueModuleFixture($compiled));

    $map = resolve(RegistryIntrospection::class)->extensionPoints();
    $encoded = json_encode($map->extensionPoints, JSON_THROW_ON_ERROR);

    $api = collect($map->extensionPoints)->firstWhere('name', 'api');

    expect($api['entryCount'])->toBe(1)
        ->and($api['owners'])->toBe(['example/tools' => 1])
        ->and($encoded)->not->toContain(StubOperationHandler::class)
        ->and($map->revision)->toBe($compiled->fingerprint);
});
