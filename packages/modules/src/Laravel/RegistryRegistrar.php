<?php

declare(strict_types=1);

namespace CoreX\Modules\Laravel;

use CoreX\Modules\Compiler\FieldCastMap;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\FieldCastRegistry;
use CoreX\Modules\Contracts\ManifestSectionCompiler;
use CoreX\Modules\Contracts\ModuleActivationGate;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistryCache;
use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Contracts\RegistryIntrospection;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Gate\AllowAllActivationGate;
use CoreX\Modules\Internal\ReadRegistryIntrospection;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\CompiledModuleRegistry;
use CoreX\Modules\Registry\RepositoryModuleRegistryCache;
use CoreX\Modules\Support\ModulesOptions;
use CoreX\Support\PackageRegistrar;
use CoreX\Support\RegistersNothingOnBoot;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;

/** @internal Composition registrar. Not package public API. */
final class RegistryRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(FieldCastRegistry::class, FieldCastMap::class);
        $app->when(RegistryCompiler::class)->needs('$sections')->giveTagged(ManifestSectionCompiler::class);

        // Bind the concrete singleton first so module providers can resolve
        // it directly to call register() (not part of the read-only contract),
        // then alias the contract to the same instance. The active-module
        // predicate is wired to the real ModuleRegistry::isActive
        // — resolved lazily at call time so all(null) at boot never triggers it
        // (and never a DB read).
        $app->singleton(ArrayEntityRegistry::class, static function (Application $app): ArrayEntityRegistry {
            return new ArrayEntityRegistry(
                moduleActive: static fn (string $module, mixed $ctx): bool => $app
                    ->make(ModuleRegistry::class)
                    ->isActive($module, $ctx),
            );
        });
        $app->singleton(
            EntityRegistry::class,
            static fn (Application $app): ArrayEntityRegistry => $app->make(ArrayEntityRegistry::class),
        );

        // No fallback modules by default — real deploys scan installed.json;
        // tests/testbench rebind this with an injected class list.
        $app->singleton(ModuleDiscovery::class);

        // Boxed default: the cloud billing gate is an application concern.
        $app->singleton(ModuleActivationGate::class, AllowAllActivationGate::class);

        // Registry cache WITHOUT tags: store/ttl/prefix from Options
        // (no hardcoded 3600). store = null ⇒ the app's default store.
        $app->singleton(ModuleRegistryCache::class, static function (Application $app): RepositoryModuleRegistryCache {
            $options = $app->make(ModulesOptions::class);

            return new RepositoryModuleRegistryCache(
                store: $app->make(CacheFactory::class)->store($options->cacheStore),
                options: $options,
            );
        });

        $app->singleton(ModuleRegistry::class, function (Application $app): CompiledModuleRegistry {
            return new CompiledModuleRegistry(
                cachePath: CompiledModuleRegistry::defaultCachePath(),
                stateReader: $app->make(ModuleStateReader::class),
                cache: $app->make(ModuleRegistryCache::class),
                // Epoch: monotonic per-account registry version read from
                // the module connection. ctx is accepted for future per-account
                // resolution; today it reads the (single) module DB.
                versionResolver: fn (mixed $ctx): int => $this->registryVersion($app),
                // Production must have compiled the registry at deploy; a
                // missing file there is a hard failure, not a silent empty
                // runtime (a defect class caught in an earlier pre-mortem).
                failIfMissing: $app->environment('production'),
            );
        });

        $app->singleton(RegistryIntrospection::class, static function (Application $app): ReadRegistryIntrospection {
            return new ReadRegistryIntrospection(
                registry: $app->make(ModuleRegistry::class),
                activationGate: $app->make(ModuleActivationGate::class),
                cachePath: CompiledModuleRegistry::defaultCachePath(),
                failIfMissing: $app->environment('production'),
            );
        });
    }

    /**
     * Monotonic registry epoch: the count of applied lifecycle
     * transitions in `mod_lifecycle_log`. The log is append-only and each
     * terminal transition (enable/disable/upgrade/archive/purge) inserts a row
     * INSIDE the lifecycle transaction, so the count is a clock-free monotonic
     * version bumped atomically with the state change — and, living in the
     * shared DB, it is visible to every process/node (which a caller-local
     * cache clear would not be). The connection comes from ModulesOptions
     * (null = app default; multi-tenancy rebinds that to the tenant DB).
     * Missing table (pre-migrate / sqlite suites) reads as epoch 0.
     *
     * Lives next to CompiledModuleRegistry, the only caller. moduleWiring
     * stays on LifecycleRegistrar.
     *
     * @internal spec: D39
     */
    private function registryVersion(Application $app): int
    {
        $connection = $app->make(ModulesOptions::class)->connection;

        if (! Schema::connection($connection)->hasTable('mod_lifecycle_log')) {
            return 0;
        }

        return (int) DB::connection($connection)->table('mod_lifecycle_log')->count();
    }
}
