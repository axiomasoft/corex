<?php

declare(strict_types=1);

namespace CoreX\Modules\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\FieldCastRegistry;
use CoreX\Modules\Contracts\ManifestSectionCompiler;
use CoreX\Modules\Contracts\ModuleActivationGate;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistryCache;
use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Contracts\RegistryIntrospection;
use CoreX\Modules\Internal\Compiler\FieldCastMap;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Modules\Internal\Discovery\ModuleDiscovery;
use CoreX\Modules\Internal\Gate\AllowAllActivationGate;
use CoreX\Modules\Internal\InMemoryEntityRegistry;
use CoreX\Modules\Internal\ReadRegistryIntrospection;
use CoreX\Modules\Internal\Registry\CompiledModuleRegistry;
use CoreX\Modules\Internal\Registry\RepositoryModuleRegistryCache;
use CoreX\Modules\Internal\Support\ModulesOptions;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RegistryRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    private const string INSTALLED_JSON = 'vendor/composer/installed.json';

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(ModulesOptions::class, static fn (Application $app): ModulesOptions => ModulesOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex.modules', []),
        ));
        $app->singleton(FieldCastRegistry::class, FieldCastMap::class);
        $app->when(RegistryCompiler::class)->needs('$sections')->giveTagged(ManifestSectionCompiler::class);
        $app->when(RegistryCompiler::class)->needs('$installedJsonPath')->give(static fn (Application $app): string => $app->basePath(self::INSTALLED_JSON));

        // Bind the concrete singleton first so module providers can resolve
        // it directly to call register() (not part of the read-only contract),
        // then alias the contract to the same instance. The active-module
        // predicate is wired to the real ModuleRegistry::isActive
        // — resolved lazily at call time so all(null) at boot never triggers it
        // (and never a DB read).
        $app->singleton(InMemoryEntityRegistry::class, static function (Application $app): InMemoryEntityRegistry {
            return new InMemoryEntityRegistry(
                moduleActive: static fn (string $module, mixed $ctx): bool => $app
                    ->make(ModuleRegistry::class)
                    ->isActive($module, $ctx),
            );
        });
        $app->singleton(
            EntityRegistry::class,
            static fn (Application $app): InMemoryEntityRegistry => $app->make(InMemoryEntityRegistry::class),
        );

        // No fallback modules by default — real deploys scan installed.json;
        // tests/testbench rebind this with an injected class list.
        $app->singleton(ModuleDiscovery::class, static fn (Application $app): ModuleDiscovery => new ModuleDiscovery(installedJsonPath: $app->basePath(self::INSTALLED_JSON)));

        // Boxed default: the cloud billing gate is an application concern.
        $app->singleton(ModuleActivationGate::class, AllowAllActivationGate::class);

        // Registry cache WITHOUT tags: store/ttl/prefix from Options
        // (no hardcoded 3600). store = null ⇒ the app's default store.
        $app->singleton(ModuleRegistryCache::class, static fn (Application $app): RepositoryModuleRegistryCache => new RepositoryModuleRegistryCache(
            store: $app->make(CacheFactory::class)->store($app->make(ModulesOptions::class)->cacheStore),
            options: $app->make(ModulesOptions::class),
        ));

        $app->singleton(ModuleRegistry::class, function (Application $app): CompiledModuleRegistry {
            return new CompiledModuleRegistry(
                cachePath: $app->basePath(CompiledModuleRegistry::CACHE_PATH),
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
                cachePath: $app->basePath(CompiledModuleRegistry::CACHE_PATH),
                failIfMissing: $app->environment('production'),
                installedJsonPath: $app->basePath(self::INSTALLED_JSON),
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
     */
    private function registryVersion(Application $app): int
    {
        $connection = $app->make(ModulesOptions::class)->connection;

        if (! Schema::connection($connection)->hasTable('mod_lifecycle_log')) {
            return 0;
        }

        return DB::connection($connection)->table('mod_lifecycle_log')->count();
    }
}
