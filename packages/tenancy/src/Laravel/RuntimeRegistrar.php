<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Context\Contracts\CentralWorkRunner;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Context\Laravel\WorkAuthentication;
use CoreX\Context\Laravel\WorkQueueHandlers;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Tenancy\Account\Internal\Jobs\CreateDatabaseFromTemplate;
use CoreX\Tenancy\Identity\Internal\Jobs\SyncIdentityToAccount;
use CoreX\Tenancy\Impersonation\Internal\ImpersonatedModelWriteGuard;
use CoreX\Tenancy\Internal\DangerousModesCheck;
use CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy;
use CoreX\Tenancy\Laravel\Support\ImpersonationPolicy;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Internal\CloudCentralWorkRunner;
use CoreX\Tenancy\Runtime\Internal\Storage\ContextTenantStorage;
use CoreX\Tenancy\Runtime\Internal\TenancyManagerContextResolver;
use CoreX\Tenancy\Runtime\Internal\TenantPhysicalBindingVerifier;
use CoreX\Tenancy\Runtime\Internal\TenantRuntimeManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Contracts\RuntimeConfigCheck;
use Packman\Foundation\Contracts\PackageRegistrar;
use Tenant\Contracts\Runtime\TenantRuntime;
use Tenant\Data\Context\ProfileRef;

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
                config: self::configSection(app: $app, key: 'corex-tenancy'),
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

        $app->singleton(TenantRuntimeManager::class);
        $app->singleton(TenancyManager::class, static fn (Application $app): TenantRuntimeManager => $app->make(TenantRuntimeManager::class));
        $app->scoped(WorkPrincipalAuthority::class, MembershipWorkPrincipal::class);
        $app->bind(CentralWorkRunner::class, static fn (Application $app): CentralWorkRunner => new CloudCentralWorkRunner(
            $app->make(TenantRuntime::class), $app->make(Container::class), $app->make(WorkAuthentication::class),
            new ProfileRef($app->make(Repository::class)->string('tenant.runtime_profile'), 1),
        ));
        $app->afterResolving(WorkQueueHandlers::class, self::declareCentralJobs(...));
        $app->singleton(TenantContextResolver::class, TenancyManagerContextResolver::class);
        $app->scoped(PhysicalBindingVerifier::class, TenantPhysicalBindingVerifier::class);
        $app->scoped(WorkScopeDirectory::class, CloudWorkScopeDirectory::class);

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

    }

    private static function declareCentralJobs(WorkQueueHandlers $handlers): void
    {
        $handlers->central(CreateDatabaseFromTemplate::class);
        $handlers->tenant(SyncIdentityToAccount::class);
    }

    #[Override]
    public function boot(Application $app): void {}

    /** @return array<string, mixed> */
    private static function configSection(Application $app, string $key): array
    {
        $section = $app->make(Repository::class)->get($key, []);

        if (! is_array($section)) {
            return [];
        }

        return array_filter($section, is_string(...), ARRAY_FILTER_USE_KEY);
    }
}
