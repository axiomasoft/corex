<?php

declare(strict_types=1);

namespace CoreX;

use CoreX\Audit\PruneAuditLog;
use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\AuditRetention;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Contracts\DataScopeCodec;
use CoreX\Contracts\DepartmentRepository;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\ModuleRegistrar;
use CoreX\Contracts\OperationObserver;
use CoreX\Contracts\PubSub;
use CoreX\Contracts\SavedViewRepository;
use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\TenantStorage;
use CoreX\Contracts\ViewAccessPolicy;
use CoreX\Contracts\ViewSchemaProvider;
use CoreX\Contracts\WorkerRuntime;
use CoreX\Messaging\Commands\PruneOutboxMessages;
use CoreX\Messaging\Commands\RelayOutbox;
use CoreX\Messaging\Commands\ShowDeliveryHealth;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\NullTraceContextBridge;
use CoreX\Messaging\Internal\SqlDeliveryHealth;
use CoreX\Messaging\Internal\SqlInbox;
use CoreX\Messaging\Internal\SqlOutbox;
use CoreX\Messaging\Internal\SqlOutboxRelay;
use CoreX\Observability\NullOperationObserver;
use CoreX\PubSub\DefaultWorkerRuntime;
use CoreX\PubSub\PrunePubSubMessages;
use CoreX\PubSub\PubSubManager;
use CoreX\Storage\LocalTenantStorage;
use CoreX\Support\Config;
use CoreX\Support\Console\ValidateConfigCommand;
use CoreX\Support\CoreOptions;
use CoreX\Support\Data\ArrayDataScopeCodec;
use CoreX\Support\ServiceProviderModuleRegistrar;
use CoreX\Support\Time\LaravelClock;
use CoreX\Tenancy\Contracts\ImpersonationService;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use CoreX\Tenancy\GuardedTenantContextRestorer;
use CoreX\Views\Internal\DenyViewAccessPolicy;
use CoreX\Views\Internal\SqlSavedViewRepository;
use CoreX\Views\Internal\UnavailableViewSchemaProvider;
use CoreX\Views\Internal\ViewConfigValidator;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\DateFactory;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Override;
use Psr\Clock\ClockInterface;

final class CoreServiceProvider extends ServiceProvider
{
    /**
     * Shipped schema generation of the sys_* migration set. A consumer
     * publishes a COPY of the migrations, so a package upgrade cannot mutate
     * them in place — schema changes ship as NEW additive migration files under
     * the same publish tag and bump this constant. Lets a host verify which
     * generation its published set is on before re-running vendor:publish.
     *
     * @internal spec: D43
     */
    public const int SCHEMA_VERSION = 1;

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex.php', 'corex');

        $raw = $this->app['config']->get('corex', []);
        $options = CoreOptions::fromArray(is_array($raw) ? $raw : []);
        $this->app->instance(CoreOptions::class, $options);
        Config::bind($options);

        $this->app->singleton(SettingDefaultsProvider::class, $options->settingDefaultsProvider);
        $this->app->singleton(SettingsRepository::class, $options->settingsRepository);
        $this->app->singleton(AuditLogger::class, $options->auditLogger);
        $this->app->singleton(AuditRetention::class, $options->auditRetention);

        $this->app->singleton(TenantContextResolver::class, $options->tenantContextResolver);
        $this->app->scoped(
            TenantContextRestorer::class,
            static fn ($app): GuardedTenantContextRestorer => new GuardedTenantContextRestorer(
                resolver: $app->make(TenantContextResolver::class),
            ),
        );
        $this->app->singleton(TenancyManager::class, $options->tenancyManager);
        $this->app->singleton(ImpersonationService::class, $options->impersonationService);
        $this->app->singleton(DataScopeAuthorizer::class, $options->dataScopeAuthorizer);
        $this->app->singleton(DataScopeCodec::class, ArrayDataScopeCodec::class);
        $this->app->singleton(
            MessageContractRegistry::class,
            static fn (): ArrayMessageContractRegistry => new ArrayMessageContractRegistry(contracts: []),
        );
        $this->app->instance(OperationObserver::class, new NullOperationObserver);
        $this->app->instance(TraceContextBridge::class, new NullTraceContextBridge);
        $this->app->scoped(
            Outbox::class,
            static fn ($app): SqlOutbox => new SqlOutbox(
                connection: $app->make(ConnectionInterface::class),
                contracts: $app->make(MessageContractRegistry::class),
                clock: $app->make(ClockInterface::class),
                traces: $app->make(TraceContextBridge::class),
            ),
        );
        $this->app->scoped(
            OutboxRelay::class,
            static fn ($app): SqlOutboxRelay => new SqlOutboxRelay(
                connection: $app->make(Connection::class),
                clock: $app->make(ClockInterface::class),
                leaseSeconds: Config::messagingLeaseSeconds(),
                maxAttempts: Config::messagingMaxAttempts(),
                backoffCapSeconds: Config::messagingBackoffCapSeconds(),
            ),
        );
        $this->app->scoped(
            Inbox::class,
            static fn ($app): SqlInbox => new SqlInbox(
                connection: $app->make(ConnectionInterface::class),
                clock: $app->make(ClockInterface::class),
            ),
        );
        $this->app->scoped(
            SqlDeliveryHealth::class,
            static fn ($app): SqlDeliveryHealth => new SqlDeliveryHealth(
                connection: $app->make(ConnectionInterface::class),
                clock: $app->make(ClockInterface::class),
            ),
        );
        $this->app->singleton(ClockInterface::class, static fn ($app): LaravelClock => new LaravelClock(
            dates: $app->make(DateFactory::class),
        ));
        $this->app->singleton(ModuleRegistrar::class, ServiceProviderModuleRegistrar::class);
        $this->app->singleton(FeatureFlags::class, $options->featureFlags);
        $this->app->singleton(DepartmentRepository::class, $options->departmentRepository);
        $this->app->singleton(PubSub::class, PubSubManager::class);
        $this->app->singleton(WorkerRuntime::class, DefaultWorkerRuntime::class);
        $this->app->singleton(ViewSchemaProvider::class, UnavailableViewSchemaProvider::class);
        $this->app->singleton(ViewAccessPolicy::class, DenyViewAccessPolicy::class);
        $this->app->singleton(ViewConfigValidator::class);
        $this->app->singleton(SavedViewRepository::class, function ($app) use ($options): SqlSavedViewRepository {
            $database = $app->make('db');

            if (! $database instanceof DatabaseManager) {
                throw new LogicException('Database manager is not bound.');
            }

            return new SqlSavedViewRepository(
                schemas: $app->make(ViewSchemaProvider::class),
                policy: $app->make(ViewAccessPolicy::class),
                validator: $app->make(ViewConfigValidator::class),
                options: $options,
                database: $database,
            );
        });
        $this->app->scoped(TenantStorage::class, function ($app): LocalTenantStorage {
            return new LocalTenantStorage(
                contexts: $app->make(TenantContextResolver::class),
                filesystems: new FilesystemManager($app),
                durableRoot: Config::storageDurableRoot(),
                ephemeralRoot: Config::storageEphemeralRoot(),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneAuditLog::class,
                PrunePubSubMessages::class,
                PruneOutboxMessages::class,
                RelayOutbox::class,
                ShowDeliveryHealth::class,
                ValidateConfigCommand::class,
            ]);
        }

        // NO loadMigrationsFrom: every sys_* migration is PG-only
        // DDL living in database/migrations/pg/, and this provider is registered
        // by sqlite-backed consumer suites too — an unconditional load would
        // break them. The old call
        // pointed at the now-empty database/migrations/ root (the scan is
        // non-recursive), so it silently loaded ZERO tables while reading as
        // "migrations are wired" — removed. Consumers get the schema through
        // publish + an explicit, tenant-scoped migrate (see README).
        //
        // Connection-scoped publish: sys_* is TENANT-schema DDL. The
        // `-tenant` tag names that scope so a consumer never runs it on the
        // central/root connection by accident (pre-mortem P1R C5) — the plain
        // `corex-migrations` alias stays for the common single-DB (boxed) case.
        // Under P2 (DB-per-account) the consumer runs
        // `migrate --database=<tenant>` after publishing.
        $this->publishes([
            __DIR__.'/../database/migrations/pg' => database_path('migrations'),
        ], ['corex-migrations', 'corex-migrations-tenant']);

        $this->publishes([
            __DIR__.'/../config/corex.php' => config_path('corex.php'),
        ], 'corex-config');
    }
}
