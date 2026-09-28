<?php

declare(strict_types=1);

use CoreX\Models\BaseModel;
use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Events\ManifestListeners;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\ModuleDependency;
use CoreX\Modules\ModuleManifest;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\CompiledModuleRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use CoreX\Tenancy\Contracts\TenantContextResolver;

final class RemediationManifestListener
{
    public static int $calls = 0;

    public function handle(stdClass $event): void
    {
        self::$calls++;
    }
}

it('resolves module activity once per entity-list operation without caching across contexts', function (): void {
    $calls = 0;
    $active = ['example/alpha'];
    $registry = new ArrayEntityRegistry(activeModules: function ($context) use (&$calls, &$active): array {
        $calls++;

        return $active;
    });
    foreach (range(1, 20) as $index) {
        $registry->register(new EntityDefinition(
            handle: 'example.entity'.$index, model: BaseModel::class, table: 'example_entities',
            module: $index < 11 ? 'example/alpha' : 'example/beta', labelSingular: 'Entity', labelPlural: 'Entities',
            capabilities: [], fields: [], titleAttribute: null,
        ));
    }
    $context = app(TenantContextResolver::class)->current();
    expect($registry->all($context))->toHaveCount(10)->and($calls)->toBe(1);
    $active = [];
    expect($registry->all($context))->toBeEmpty()->and($calls)->toBe(2);
    expect($registry->all())->toHaveCount(20)->and($calls)->toBe(2);
});

it('rejects a dependency cycle with its chain before compilation', function (): void {
    $a = ModuleManifest::make('example/alpha')->title('A')->tablePrefix('aaa')->requires([ModuleDependency::module('example/beta')]);
    $b = ModuleManifest::make('example/beta')->title('B')->tablePrefix('bbb')->requires([ModuleDependency::module('example/alpha')]);
    expect(fn () => (new RegistryCompiler)->compile([$a, $b], []))
        ->toThrow(CompilationException::class, 'example/alpha -> example/beta -> example/alpha');
});

it('dispatches compiled manifest listeners once only while their owning module is active', function (): void {
    RemediationManifestListener::$calls = 0;
    $manifest = ModuleManifest::make('example/listener')->title('Listener')->tablePrefix('lst')
        ->listens([stdClass::class => [RemediationManifestListener::class]]);
    $compiled = CompiledRegistry::fromArray((new RegistryCompiler)->compile([$manifest], [])->toArray());
    $active = false;
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('compiled')->andReturn($compiled);
    $registry->shouldReceive('isActive')->andReturnUsing(function () use (&$active): bool {
        return $active;
    });
    $listeners = new ManifestListeners(app('events'), $registry, app(TenantContextResolver::class));
    $listeners->register($compiled);
    $listeners->register($compiled);
    event(new stdClass);
    expect(RemediationManifestListener::$calls)->toBe(0);
    $active = true;
    event(new stdClass);
    expect(RemediationManifestListener::$calls)->toBe(1);
    $active = false;
    event(new stdClass);
    expect(RemediationManifestListener::$calls)->toBe(1);
});

it('can compile a missing production registry while ordinary console boot still fails', function (): void {
    $previousArgv = $_SERVER['argv'];
    $this->app->detectEnvironment(static fn (): string => 'production');
    $this->app->useBootstrapPath(database_path('bootstrap'));

    try {
        $_SERVER['argv'] = ['artisan', 'corex:modules:compile'];
        $this->app->register(ModulesServiceProvider::class);
        $this->app->instance(ModuleDiscovery::class, new ModuleDiscovery(installedJsonPath: '/nonexistent-test-installed.json'));
        $this->artisan('corex:modules:compile')->assertSuccessful();
        $path = CompiledModuleRegistry::defaultCachePath();
        expect(is_file($path))->toBeTrue();
        unlink($path);
        app(ModuleRegistry::class)->flush();
        $_SERVER['argv'] = ['artisan', 'list'];
        expect(fn () => (new ModulesServiceProvider($this->app))->boot())->toThrow(RuntimeException::class);
    } finally {
        $_SERVER['argv'] = $previousArgv;
        $this->app->detectEnvironment(static fn (): string => 'testing');
    }
});
