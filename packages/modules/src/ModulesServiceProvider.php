<?php

declare(strict_types=1);

namespace Flex\Modules;

use Flex\Modules\Commands\CompileModulesCommand;
use Flex\Modules\Contracts\EntityRegistry;
use Flex\Modules\Contracts\ModuleActivationGate;
use Flex\Modules\Contracts\ModuleRegistry;
use Flex\Modules\Contracts\ModuleStateReader;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Gate\AllowAllActivationGate;
use Flex\Modules\Registry\ArrayEntityRegistry;
use Flex\Modules\Registry\ArrayModuleStateReader;
use Flex\Modules\Registry\CompiledModuleRegistry;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Override;

final class ModulesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        // Bind the concrete singleton first so module providers can resolve
        // it directly to call register() (not part of the read-only contract),
        // then alias the contract to the same instance.
        $this->app->singleton(ArrayEntityRegistry::class);
        $this->app->singleton(
            EntityRegistry::class,
            static fn (Application $app): ArrayEntityRegistry => $app->make(ArrayEntityRegistry::class),
        );

        // No fallback modules by default — real deploys scan installed.json;
        // tests/testbench rebind this with an injected class list (P1.3 seam).
        $this->app->singleton(ModuleDiscovery::class);

        // Boxed defaults (D10): empty enabled-set reader, allow-all gate.
        // The DB-backed reader and the cloud gate are P1.6/application concerns.
        $this->app->singleton(ModuleStateReader::class, ArrayModuleStateReader::class);
        $this->app->singleton(ModuleActivationGate::class, AllowAllActivationGate::class);

        $this->app->singleton(ModuleRegistry::class, static function (Application $app): CompiledModuleRegistry {
            return new CompiledModuleRegistry(
                cachePath: CompiledModuleRegistry::defaultCachePath(),
                stateReader: $app->make(ModuleStateReader::class),
                cache: $app->make(CacheFactory::class)->store(),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CompileModulesCommand::class]);
        }

        // Built after every module provider has booted and registered its
        // entities (ADR-003: EntityRegistry is the single morph-map source).
        $this->app->booted(function (): void {
            $map = [];

            foreach ($this->app->make(EntityRegistry::class)->all() as $definition) {
                $map[$definition->handle] = $definition->model;
            }

            if ($map !== []) {
                Relation::morphMap($map);
            }
        });
    }
}
