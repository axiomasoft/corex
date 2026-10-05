<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Impersonation\Internal\ImpersonatedModelWriteGuard;
use CoreX\Tenancy\Internal\DangerousModesCheck;
use CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy;
use CoreX\Tenancy\Laravel\Support\ImpersonationPolicy;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Contracts\TemplateDatabaseCloner;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateClonePostgreSQLManager;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\CachePrefixBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\ConnectionBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\SessionConnectionBootstrapper;
use CoreX\Tenancy\Runtime\Internal\CentralTemplateConnectionResolver;
use CoreX\Tenancy\Runtime\Internal\ConnectionGuardedTenantContextRestorer;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use CoreX\Tenancy\Runtime\Internal\FrameworkConfig;
use CoreX\Tenancy\Runtime\Internal\QueueTenantContext;
use CoreX\Tenancy\Runtime\Internal\Storage\ContextTenantStorage;
use CoreX\Tenancy\Runtime\Internal\TenancyManagerContextResolver;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Queue\QueueManager;
use Illuminate\Session\SessionManager;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RuntimeRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        // Lazy singleton, never instance(): materializing TenancyOptions here would
        // validate OIDC before Testbench fills it; the provider resolves it in boot().
        $app->singleton(
            TenancyOptions::class,
            static fn (Application $app): TenancyOptions => TenancyOptions::fromArray(
                config: self::tenancySection($app),
            ),
        );
        $app->bind(
            ImpersonationPolicy::class,
            static fn (Application $app): ImpersonationPolicy => $app->make(TenancyOptions::class)->impersonation,
        );
        $app->scoped(ImpersonatedModelWriteGuard::class);
        $app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);
        $app->bind(
            AccountLifecyclePolicy::class,
            static fn (Application $app): AccountLifecyclePolicy => $app->make(TenancyOptions::class)->lifecycle,
        );
        $app->bind(
            StatusGatePolicy::class,
            static fn (Application $app): StatusGatePolicy => $app->make(TenancyOptions::class)->statusGate,
        );

        $app->singleton(
            FrameworkConfig::class,
            static fn (): FrameworkConfig => TenancyRuntimeFactory::frameworkConfig(),
        );

        $app->bindIf(
            AccountConnectionResolver::class,
            static fn (Application $app): AccountConnectionResolver => new CentralTemplateConnectionResolver(
                connections: $app->make(ConnectionResolverInterface::class),
                config: $app->make(FrameworkConfig::class),
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
            ),
        );

        $app->singleton(
            ConnectionBootstrapper::class,
            static fn (Application $app): ConnectionBootstrapper => new ConnectionBootstrapper(
                databases: $app->make(DatabaseManager::class),
                config: $app->make(FrameworkConfig::class),
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
            ),
        );
        $app->singleton(
            CachePrefixBootstrapper::class,
            static fn (Application $app): CachePrefixBootstrapper => new CachePrefixBootstrapper(
                cache: $app->make(CacheManager::class),
                config: $app->make(FrameworkConfig::class),
                stores: $app->make(TenancyOptions::class)->cacheStores,
                prefix: $app->make(TenancyOptions::class)->cachePrefix,
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
            ),
        );
        $app->singleton(
            SessionConnectionBootstrapper::class,
            static fn (Application $app): SessionConnectionBootstrapper => new SessionConnectionBootstrapper(
                sessions: $app->make(SessionManager::class),
                cache: $app->make(CacheManager::class),
                container: $app,
                config: $app->make(FrameworkConfig::class),
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                files: $app->make('files'),
            ),
        );

        $app->singleton(
            DatabaseTenancyManager::class,
            static fn (Application $app): DatabaseTenancyManager => TenancyRuntimeFactory::manager($app),
        );
        $app->singleton(
            TenancyManager::class,
            static fn (Application $app): DatabaseTenancyManager => $app->make(DatabaseTenancyManager::class),
        );
        $app->singleton(
            QueueTenantContext::class,
            static fn (Application $app): QueueTenantContext => new QueueTenantContext(
                manager: $app->make(TenancyManager::class),
                accounts: $app->make(AccountRead::class),
                workspaceResolver: TenancyRuntimeFactory::workspaceResolver(),
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
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

        $app->bind(TemplateDatabaseCloner::class, TemplateClonePostgreSQLManager::class);

        $app->bind(
            TemplateClonePostgreSQLManager::class,
            static fn (Application $app): TemplateClonePostgreSQLManager => new TemplateClonePostgreSQLManager(
                options: $app->make(TenancyOptions::class),
                connections: $app->make(ConnectionResolverInterface::class),
                integrity: $app->make(TemplateIntegrityVerifier::class),
            ),
        );
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(QueueManager::class)->createPayloadUsing(TenancyRuntimeFactory::payloadHook());
    }

    /**
     * `cache_stores = null` means the application's default cache store: the secure default is to
     * isolate what the application actually uses.
     *
     * @return array<string, mixed>
     */
    private static function tenancySection(Application $app): array
    {
        $section = self::configSection($app, 'corex-tenancy');

        if (($section['cache_stores'] ?? null) === null) {
            $default = TenancyRuntimeFactory::frameworkConfig()->get('cache.default');
            $section['cache_stores'] = is_string($default) && $default !== '' ? [$default] : [];
        }

        return $section;
    }

    /** @return array<string, mixed> */
    private static function configSection(Application $app, string $key): array
    {
        $section = $app['config']->get($key, []);

        if (! is_array($section)) {
            return [];
        }

        return array_filter($section, static fn (mixed $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }
}
