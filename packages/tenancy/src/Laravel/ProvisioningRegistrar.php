<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantDatabaseLifecycle;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Laravel\RegistersNothingOnBoot;
use CoreX\Tenancy\Account\Internal\DatabaseTenantProvisioner;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycle;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycleFsm;
use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Account\Laravel\MigrationWaveOrchestrator;
use CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy;
use CoreX\Tenancy\Laravel\Support\HostResolverCacheInvalidator;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Internal\DatabaseTenantLifecycle;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Override;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ProvisioningRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(
            DatabaseTenantProvisioner::class,
            static fn (Application $app): DatabaseTenantProvisioner => new DatabaseTenantProvisioner(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                templateIntegrityVerifier: $app->make(TemplateIntegrityVerifier::class),
            ),
        );

        $app->singleton(
            PendingPool::class,
            static fn (Application $app): PendingPool => new PendingPool(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                provisioner: $app->make(DatabaseTenantProvisioner::class),
                templateIntegrityVerifier: $app->make(TemplateIntegrityVerifier::class),
                connections: $app->make(ConnectionResolverInterface::class),
            ),
        );

        $app->singleton(
            DatabaseTenantLifecycle::class,
            static fn (Application $app): DatabaseTenantLifecycle => new DatabaseTenantLifecycle(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                connections: $app->make(ConnectionResolverInterface::class),
                clock: $app->make(ClockInterface::class),
                events: $app->make(Dispatcher::class),
            ),
        );
        $app->singleton(
            TenantDatabaseLifecycle::class,
            static fn (Application $app): DatabaseTenantLifecycle => $app->make(
                DatabaseTenantLifecycle::class,
            ),
        );

        $app->scoped(
            AccountLifecycle::class,
            static fn (Application $app): AccountLifecycle => new AccountLifecycle(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                pendingPool: $app->make(PendingPool::class),
                fsm: $app->make(AccountLifecycleFsm::class),
                tenantDatabaseLifecycle: $app->make(TenantDatabaseLifecycle::class),
                impersonationGuard: $app->make(ImpersonationGuard::class),
                templateIntegrityVerifier: $app->make(TemplateIntegrityVerifier::class),
                lifecycle: $app->make(AccountLifecyclePolicy::class),
                connections: $app->make(ConnectionResolverInterface::class),
                clock: $app->make(ClockInterface::class),
                events: $app->make(Dispatcher::class),
                connectionFactory: $app->make(ConnectionFactory::class),
                hostCache: $app->make(HostResolverCacheInvalidator::class),
            ),
        );

        $app->singleton(
            MigrationWaveOrchestrator::class,
            static fn (Application $app): MigrationWaveOrchestrator => new MigrationWaveOrchestrator(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                tenancy: $app->make(TenancyManager::class),
                templateIntegrityVerifier: $app->make(TemplateIntegrityVerifier::class),
                schema: $app->make(SchemaMigrator::class),
            ),
        );
    }
}
