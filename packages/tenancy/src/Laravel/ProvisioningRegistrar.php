<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantDatabaseLifecycle;
use CoreX\Context\ImpersonationGuard;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\RegistersNothingOnBoot;
use CoreX\Tenancy\Account\Lifecycle\AccountLifecycle;
use CoreX\Tenancy\Account\Lifecycle\AccountLifecycleFsm;
use CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Database\TemplateIntegrityVerifier as DatabaseTemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Migrations\MigrationWaveOrchestrator;
use CoreX\Tenancy\Provisioning\PendingPool;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseLifecycle;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseProvisioner;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal Package composition type. */
final class ProvisioningRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(
            StanclTenantDatabaseProvisioner::class,
            static fn (Application $app): StanclTenantDatabaseProvisioner => new StanclTenantDatabaseProvisioner(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                templateIntegrityVerifier: $app->make(DatabaseTemplateIntegrityVerifier::class),
            ),
        );

        $app->singleton(
            PendingPool::class,
            static fn (Application $app): PendingPool => new PendingPool(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                provisioner: $app->make(StanclTenantDatabaseProvisioner::class),
                templateIntegrityVerifier: $app->make(DatabaseTemplateIntegrityVerifier::class),
            ),
        );

        $app->singleton(
            StanclTenantDatabaseLifecycle::class,
            static fn (Application $app): StanclTenantDatabaseLifecycle => new StanclTenantDatabaseLifecycle(
                $app->make(TenancyOptions::class)->centralConnection,
            ),
        );
        $app->singleton(
            TenantDatabaseLifecycle::class,
            static fn (Application $app): StanclTenantDatabaseLifecycle => $app->make(
                StanclTenantDatabaseLifecycle::class,
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
                templateIntegrityVerifier: $app->make(DatabaseTemplateIntegrityVerifier::class),
                lifecycle: $app->make(AccountLifecyclePolicy::class),
            ),
        );

        $app->singleton(
            MigrationWaveOrchestrator::class,
            static fn (Application $app): MigrationWaveOrchestrator => new MigrationWaveOrchestrator(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                tenancy: $app->make(TenancyManager::class),
                templateIntegrityVerifier: $app->make(DatabaseTemplateIntegrityVerifier::class),
            ),
        );
    }
}
