<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Tenancy\Laravel\Bootstrappers\WorkspaceContextBootstrapper;
use CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy;
use CoreX\Tenancy\Laravel\Support\ImpersonationPolicy;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Database\TemplateClonePostgreSQLManager;
use CoreX\Tenancy\Runtime\Internal\ConnectionGuardedTenantContextRestorer;
use CoreX\Tenancy\Runtime\Internal\StanclTenancyManager;
use CoreX\Tenancy\Runtime\Internal\Storage\ContextTenantStorage;
use CoreX\Tenancy\Runtime\Internal\TenancyManagerContextResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Override;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;
use Stancl\Tenancy\Tenancy as Stancl;

/** @internal Composition registrar. Not package public API. */
final class RuntimeRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        // bind(), not instance(): each make() re-reads config. Materializing
        // TenancyOptions here would validate OIDC before Testbench fills it.
        $app->bind(
            TenancyOptions::class,
            static function (Application $app): TenancyOptions {
                $tenancy = $app['config']->get('tenancy', []);
                $corexTenancy = $app['config']->get('corex-tenancy', []);

                return TenancyOptions::fromArray(
                    tenancy: is_array($tenancy) ? $tenancy : [],
                    corexTenancy: is_array($corexTenancy) ? $corexTenancy : [],
                );
            },
        );
        $app->bind(
            ImpersonationPolicy::class,
            static fn (Application $app): ImpersonationPolicy => $app->make(TenancyOptions::class)->impersonation,
        );
        $app->bind(
            AccountLifecyclePolicy::class,
            static fn (Application $app): AccountLifecyclePolicy => $app->make(TenancyOptions::class)->lifecycle,
        );
        $app->bind(
            StatusGatePolicy::class,
            static fn (Application $app): StatusGatePolicy => $app->make(TenancyOptions::class)->statusGate,
        );

        $app->singleton(
            StanclTenancyManager::class,
            static fn (Application $app): StanclTenancyManager => new StanclTenancyManager(
                $app->make(Stancl::class),
            ),
        );
        $app->singleton(
            TenancyManager::class,
            static fn (Application $app): StanclTenancyManager => $app->make(
                StanclTenancyManager::class,
            ),
        );
        $app->singleton(TenantContextResolver::class, TenancyManagerContextResolver::class);
        $app->extend(
            TenantContextRestorer::class,
            static fn (TenantContextRestorer $semantic, Application $app): ConnectionGuardedTenantContextRestorer => new ConnectionGuardedTenantContextRestorer(
                semantic: $semantic,
                database: $app->make(DatabaseManager::class),
            ),
        );

        $app->scoped(
            TenantStorage::class,
            static fn (Application $app): ContextTenantStorage => new ContextTenantStorage(
                contexts: $app->make(TenantContextResolver::class),
                filesystems: new FilesystemManager($app),
                durableDriver: $app->make(TenancyOptions::class)->storageDurableDriver,
                durableRoot: $app->make(TenancyOptions::class)->storageDurableRoot,
                ephemeralRoot: $app->make(TenancyOptions::class)->storageEphemeralRoot,
            ),
        );

        // TenancyOptions stays bind()-lazy (P7.2): materializing it here would
        // validate OIDC before Testbench defineEnvironment fills issuer fields.
        // bootstrapper_order is read from the already-merged composition config,
        // same source the provider used before this move.
        $tenancy = $app['config']->get('tenancy', []);
        $tenancy = is_array($tenancy) ? $tenancy : [];
        /** @var list<class-string> $bootstrapperOrder */
        $bootstrapperOrder = is_array($tenancy['bootstrapper_order'] ?? null)
            ? $tenancy['bootstrapper_order']
            : [];

        // WorkspaceContextBootstrapper is not in stancl's default list, so
        // __constructStatic runs here exactly once. Stancl defaults already
        // ran theirs in their own provider — calling them again would
        // double-register queue listeners.
        foreach ($bootstrapperOrder as $bootstrapper) {
            if ($bootstrapper === WorkspaceContextBootstrapper::class) {
                WorkspaceContextBootstrapper::__constructStatic($app);
            }

            $app->singleton($bootstrapper);
        }

        $app->bind(
            TemplateClonePostgreSQLManager::class,
            static fn (Application $app): TemplateClonePostgreSQLManager => new TemplateClonePostgreSQLManager(
                options: $app->make(TenancyOptions::class),
            ),
        );
    }

    #[Override]
    public function boot(Application $app): void
    {
        $options = $app->make(TenancyOptions::class);

        // Projected in boot so this wins over stancl's own register()
        // regardless of provider order.
        $app['config']->set([
            'tenancy.models.tenant' => $options->tenantModel,
            'tenancy.database.central_connection' => $options->centralConnection,
            'tenancy.database.managers.pgsql' => $options->tenantDatabaseManager,
            'tenancy.bootstrappers' => $options->bootstrapperOrder,
            'tenancy.cache.prefix' => $options->cachePrefix,
        ]);

        $app->bind(UniqueIdentifierGenerator::class, $options->idGenerator);
    }
}
