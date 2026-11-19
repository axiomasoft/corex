<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Foundation\Internal\PackmanFeatureFlags;
use CoreX\Foundation\Laravel\PackmanRegistrar;
use CoreX\Tests\Stubs\Lifecycle\StubBaseModule;
use CoreX\Tests\Stubs\StubModuleDefinition;
use Illuminate\Filesystem\Filesystem;
use Packman\Context\Contracts\ContextResolverInterface;
use Packman\Features\Contracts\FeatureFlagsInterface;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\ModulesServiceProvider;
use Packman\Schema\Contracts\Migration\SchemaSets;

it('adapts the current account and domain feature store to Packman', function (): void {
    $core = resolve(TenantContextResolver::class)->current();
    $external = resolve(ContextResolverInterface::class)->current();

    expect($external)->toEqual($core)
        ->and($external->id())->toBe($core->account->id)
        ->and(resolve(FeatureFlagsInterface::class))->toBeInstanceOf(PackmanFeatureFlags::class);
});

it('registers one upstream control-plane schema and the independent CoreX schema', function (): void {
    $sets = resolve(SchemaSets::class);
    $modules = $sets->get('axiomasoft/packman-modules:tenant');
    $core = $sets->get('axiomasoft/corex:tenant');

    expect($modules->package)->toBe('axiomasoft/packman-modules')
        ->and($core->package)->toBe('axiomasoft/corex')
        ->and(glob($core->path.'/*create_mod_*.php'))->toBe([])
        ->and(glob($modules->path.'/*create_mod_*.php'))->toHaveCount(3);
});

it('discovers and compiles corex-module packages via extra.corex.module while preserving host overrides', function (): void {
    expect(config('modules.package_types'))->toBe([
        'corex-module',
        'corex-plugin',
        'packman-module',
        'packman-plugin',
        'module',
        'plugin',
    ])->and(config('modules.extra_keys'))->toBe([
        ['corex', 'module'],
        ['corex', 'plugin'],
        ['packman', 'module'],
        ['packman', 'plugin'],
        ['module'],
        ['plugin'],
    ]);

    $files = new Filesystem;
    $sandbox = sys_get_temp_dir().'/corex-module-discovery-'.bin2hex(random_bytes(8));
    $previousBasePath = $this->app->basePath();

    try {
        $files->makeDirectory($sandbox.'/vendor/composer', 0755, true);
        $files->makeDirectory($sandbox.'/bootstrap/cache', 0755, true);
        $files->put($sandbox.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                [
                    'name' => 'axiomasoft/packman-modules',
                    'version' => '0.1.0',
                    'type' => 'library',
                ],
                [
                    'name' => 'corex/stub-module',
                    'version' => '1.0.0',
                    'type' => 'corex-module',
                    'extra' => [
                        'corex' => [
                            'module' => StubModuleDefinition::class,
                        ],
                    ],
                ],
                [
                    'name' => 'corex/stub-base',
                    'version' => '1.0.0',
                    'type' => 'module',
                    'extra' => ['module' => StubBaseModule::class],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->app->setBasePath($sandbox);
        new ModulesServiceProvider($this->app)->register();

        $manifests = resolve(ModuleDiscovery::class)->discover();
        expect(array_map(static fn ($manifest): string => $manifest->composerName, $manifests))
            ->toEqualCanonicalizing(['corex/stub-module', 'corex/stub-base']);

        $this->artisan('modules:compile')->assertExitCode(0);
        expect(is_file($sandbox.'/bootstrap/cache/modules.php'))->toBeTrue()
            ->and(resolve(ModuleRegistry::class)->get('corex/stub-module')->composerName)->toBe('corex/stub-module');

        config([
            'modules.package_types' => ['host-custom-module'],
            'modules.extra_keys' => [['host', 'module']],
        ]);
        (new PackmanRegistrar)->register($this->app);

        expect(config('modules.package_types'))->toBe(['host-custom-module'])
            ->and(config('modules.extra_keys'))->toBe([['host', 'module']]);
    } finally {
        $this->app->setBasePath($previousBasePath);
        $files->deleteDirectory($sandbox);
    }
});
