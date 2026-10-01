<?php

declare(strict_types=1);

namespace CoreX;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Contracts\AuditRetention;
use CoreX\Audit\Internal\PruneAuditLog;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Internal\Commands\ValidateConfigCommand;
use CoreX\Config\Internal\Config;
use CoreX\Config\Internal\KernelConfigSection;
use CoreX\Config\Laravel\CoreOptionsParser;
use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Departments\Contracts\DepartmentRepository;
use CoreX\Features\Contracts\FeatureFlags;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Contracts\PubSub;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Contracts\WorkerRuntime;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\Commands\PruneOutboxMessages;
use CoreX\Messaging\Internal\Commands\RelayOutbox;
use CoreX\Messaging\Internal\Commands\ShowDeliveryHealth;
use CoreX\Messaging\Internal\DefaultWorkerRuntime;
use CoreX\Messaging\Internal\NullTraceContextBridge;
use CoreX\Messaging\Internal\PrunePubSubMessages;
use CoreX\Messaging\Internal\PubSubManager;
use CoreX\Messaging\Internal\SqlDeliveryHealth;
use CoreX\Messaging\Internal\SqlInbox;
use CoreX\Messaging\Internal\SqlOutbox;
use CoreX\Messaging\Internal\SqlOutboxRelay;
use CoreX\Modules\Contracts\ModuleRegistrar;
use CoreX\Modules\Internal\ServiceProviderModuleRegistrar;
use CoreX\Observability\Contracts\OperationObserver;
use CoreX\Observability\Contracts\TracePropagation;
use CoreX\Observability\Internal\NullOperationObserver;
use CoreX\Observability\Internal\OpenTelemetryBridge;
use CoreX\Settings\Contracts\SettingDefaultsProvider;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Internal\LocalTenantStorage;
use CoreX\Time\Laravel\LaravelClock;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\DateFactory;
use Illuminate\Support\ServiceProvider;
use Override;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CoreServiceProvider extends ServiceProvider
{
    /**
     * Shipped schema generation of the sys_* migration set. A consumer
     * publishes a COPY of the migrations, so a package upgrade cannot mutate
     * them in place — schema changes ship as NEW additive migration files under
     * the same publish tag and bump this constant. Lets a host verify which
     * generation its published set is on before re-running vendor:publish.
     */
    public const int SCHEMA_VERSION = 1;

    #[Override]
    public function register(): void
    {
        $this->app->bind(TracePropagation::class, static fn (Application $app): TracePropagation => $app->make(OpenTelemetryBridge::class));

        $this->app->singleton(CoreOptionsParser::class);
        $this->app->bind(ImpersonationGuard::class, Context\Internal\ImpersonationGuard::class);
        $this->app->tag([KernelConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex.php', 'corex');

        $raw = $this->app['config']->get('corex', []);
        $options = CoreOptionsParser::fromArray(is_array($raw) ? $raw : []);
        $this->app->bind(CoreOptions::class, static function (Application $app): CoreOptions {
            $raw = $app->make(Repository::class)->get('corex', []);

            return CoreOptionsParser::fromArray(is_array($raw) ? $raw : []);
        });
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
                leaseSeconds: app(CoreOptions::class)->messagingLeaseSeconds,
                maxAttempts: app(CoreOptions::class)->messagingMaxAttempts,
                backoffCapSeconds: app(CoreOptions::class)->messagingBackoffCapSeconds,
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
        $this->app->scoped(TenantStorage::class, function ($app): LocalTenantStorage {
            return new LocalTenantStorage(
                contexts: $app->make(TenantContextResolver::class),
                filesystems: new FilesystemManager($app),
                durableRoot: app(CoreOptions::class)->storageDurableRoot,
                ephemeralRoot: app(CoreOptions::class)->storageEphemeralRoot,
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
