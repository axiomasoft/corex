<?php

declare(strict_types=1);

namespace Flex\Modules;

use CoreX\Tenancy\TenantContext;
use Flex\Modules\Audit\AuditCapabilityWiring;
use Flex\Modules\Commands\CompileModulesCommand;
use Flex\Modules\Contracts\EntityRegistry;
use Flex\Modules\Contracts\ModuleActivationGate;
use Flex\Modules\Contracts\ModuleLifecycle;
use Flex\Modules\Contracts\ModuleRegistry;
use Flex\Modules\Contracts\ModuleRegistryCache;
use Flex\Modules\Contracts\ModuleStateReader;
use Flex\Modules\Contracts\RecordsRegistrar;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Gate\AllowAllActivationGate;
use Flex\Modules\Lifecycle\DatabaseModuleLifecycle;
use Flex\Modules\Lifecycle\DatabaseModuleStateReader;
use Flex\Modules\Lifecycle\DatabaseRecordsRegistrar;
use Flex\Modules\Registry\ArrayEntityRegistry;
use Flex\Modules\Registry\CompiledModuleRegistry;
use Flex\Modules\Registry\RepositoryModuleRegistryCache;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Override;

final class ModulesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flex-modules.php', 'flex-modules');

        // Bind the concrete singleton first so module providers can resolve
        // it directly to call register() (not part of the read-only contract),
        // then alias the contract to the same instance. The active-module
        // predicate is wired to the real ModuleRegistry::isActive (P1.16, A38)
        // — resolved lazily at call time so all(null) at boot never triggers it
        // (and never a DB read).
        $this->app->singleton(ArrayEntityRegistry::class, static function (Application $app): ArrayEntityRegistry {
            return new ArrayEntityRegistry(
                moduleActive: static fn (string $module, TenantContext $ctx): bool => $app->make(ModuleRegistry::class)->isActive($module, $ctx),
            );
        });
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

        // Registry cache WITHOUT tags (D30/A1/A2): store/ttl/prefix from config
        // (A45/A46 — no hardcoded 3600). store = null ⇒ the app's default store.
        $this->app->singleton(ModuleRegistryCache::class, static function (Application $app): RepositoryModuleRegistryCache {
            /** @var string|null $store */
            $store = config('flex-modules.cache.store');

            return new RepositoryModuleRegistryCache(
                store: $app->make(CacheFactory::class)->store($store),
                ttl: (int) config('flex-modules.cache.ttl', 3600),
                prefix: (string) config('flex-modules.cache.prefix', 'flex-modules'),
            );
        });

        $this->app->singleton(ModuleRegistry::class, function (Application $app): CompiledModuleRegistry {
            return new CompiledModuleRegistry(
                cachePath: CompiledModuleRegistry::defaultCachePath(),
                stateReader: $app->make(ModuleStateReader::class),
                cache: $app->make(ModuleRegistryCache::class),
                // Epoch (D39): monotonic per-account registry version read from
                // the module connection. ctx is accepted for the P2 per-account
                // resolution; P1 reads the (single) module DB.
                versionResolver: fn (TenantContext $ctx): int => $this->registryVersion(),
                // Production must have compiled the registry at deploy; a
                // missing file there is a hard failure, not a silent empty
                // runtime (A10, pre-mortem P1R кластер C).
                failIfMissing: $app->environment('production'),
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

        $this->publishes([
            __DIR__.'/../config/flex-modules.php' => config_path('flex-modules.php'),
        ], 'flex-modules-config');

        // Epoch memo reset (D39): a long-running queue worker drops its
        // per-process compiled-file memo before each job, so a redeploy's
        // recompiled registry is picked up instead of served stale for hours.
        $this->app['events']->listen(JobProcessing::class, function (): void {
            // Typed as the CONTRACT on purpose: a consumer may rebind
            // ModuleRegistry to an implementation without flush(), so the
            // instanceof stays a real guard (and larastan does not narrow the
            // container binding to the concrete, flagging it always-true).
            /** @var ModuleRegistry $registry */
            $registry = $this->app->make(ModuleRegistry::class);

            if ($registry instanceof CompiledModuleRegistry) {
                $registry->flush();
            }
        });

        // Deploy hook: `php artisan optimize` recompiles the registry (B-10
        // §5.2). No clear command — `optimize:clear` leaves the file, the
        // next `optimize` overwrites it atomically.
        $this->optimizes(optimize: 'flex:modules:compile', key: 'flex-modules');

        // Built after every module provider has booted and registered its
        // entities (ADR-003: EntityRegistry is the single morph-map source).
        $this->app->booted(function (): void {
            $arrayRegistry = $this->app->make(ArrayEntityRegistry::class);

            // Project the persisted compiled registry into the runtime
            // EntityRegistry so module-declared entities reach the app (A10) —
            // not only entities a provider register()'d imperatively.
            $compiled = $this->app->make(ModuleRegistry::class)->compiled();

            foreach ($compiled->entities as $definition) {
                $arrayRegistry->register($definition);
            }

            $registry = $this->app->make(EntityRegistry::class);
            $map = [];

            // Morph map spans the WHOLE registry (all() без ctx), never just
            // the active modules: reading a persisted row of a currently
            // disabled module must still resolve its morph alias, or
            // Relation::morphMap loses the handle. The active-module filter in
            // all(?ctx) is a DIFFERENT call, wired by P1.16 — not this one.
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

    /**
     * Monotonic registry epoch (D39): the count of applied lifecycle
     * transitions in `mod_lifecycle_log`. The log is append-only and each
     * terminal transition (enable/disable/upgrade/archive/purge) inserts a row
     * INSIDE the lifecycle transaction, so the count is a clock-free monotonic
     * version bumped atomically with the state change — and, living in the
     * shared DB, it is visible to every process/node (which a caller-local
     * cache clear would not be). The connection comes from
     * `config('flex-modules.connection')` (null = app default; under P2 stancl
     * rebinds that to the tenant DB). Missing table (pre-migrate / sqlite
     * suites) reads as epoch 0.
     */
    private function registryVersion(): int
    {
        /** @var string|null $connection */
        $connection = config('flex-modules.connection');

        if (! Schema::connection($connection)->hasTable('mod_lifecycle_log')) {
            return 0;
        }

        return (int) DB::connection($connection)->table('mod_lifecycle_log')->count();
    }
}
