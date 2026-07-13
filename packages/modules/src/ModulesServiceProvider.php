<?php

declare(strict_types=1);

namespace Flex\Modules;

use Flex\Modules\Audit\AuditCapabilityWiring;
use Flex\Modules\Commands\CompileModulesCommand;
use Flex\Modules\Contracts\EntityRegistry;
use Flex\Modules\Contracts\ModuleActivationGate;
use Flex\Modules\Contracts\ModuleLifecycle;
use Flex\Modules\Contracts\ModuleRegistry;
use Flex\Modules\Contracts\ModuleStateReader;
use Flex\Modules\Contracts\RecordsRegistrar;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Gate\AllowAllActivationGate;
use Flex\Modules\Lifecycle\DatabaseModuleLifecycle;
use Flex\Modules\Lifecycle\DatabaseModuleStateReader;
use Flex\Modules\Lifecycle\DatabaseRecordsRegistrar;
use Flex\Modules\Registry\ArrayEntityRegistry;
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

        // Boxed default (D10): the cloud billing gate is an application concern.
        $this->app->singleton(ModuleActivationGate::class, AllowAllActivationGate::class);

        // mod_modules/mod_records now exist (P1.6) — read/write them directly;
        // ArrayModuleStateReader remains available for tests that inject a fake.
        $this->app->singleton(ModuleStateReader::class, DatabaseModuleStateReader::class);
        $this->app->singleton(RecordsRegistrar::class, DatabaseRecordsRegistrar::class);
        $this->app->singleton(ModuleLifecycle::class, DatabaseModuleLifecycle::class);

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
        // NOT loadMigrationsFrom: mod_* is PG-only DDL (D12 — CHECK
        // constraints, advisory locks) and this provider is registered by
        // every consumer, including sqlite-backed suites (Crm/Tasks). The
        // pg-lane test/deploy path runs these migrations explicitly,
        // scoped by --path (see tests/Feature/ModuleLifecycleTest.php).
        if ($this->app->runningInConsole()) {
            $this->commands([CompileModulesCommand::class]);
        }

        // Built after every module provider has booted and registered its
        // entities (ADR-003: EntityRegistry is the single morph-map source).
        $this->app->booted(function (): void {
            $registry = $this->app->make(EntityRegistry::class);
            $map = [];

            foreach ($registry->all() as $definition) {
                $map[$definition->handle] = $definition->model;
            }

            if ($map !== []) {
                Relation::morphMap($map);
            }

            // AuditObserver lives in corex/core (B-10 §4.4 capability
            // 'auditable') — the wiring itself lives in flex/modules'
            // AuditCapabilityWiring because it needs EntityRegistry, and
            // corex/core cannot depend on flex/modules (reverse of this
            // package's own require, D22).
            AuditCapabilityWiring::wire($registry);
        });
    }
}
