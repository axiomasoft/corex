<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Laravel\ConfiguredImplementation;
use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Context\Internal\ImpersonationGuard as ImpersonationGuardImplementation;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ContextRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->bind(ImpersonationGuard::class, ImpersonationGuardImplementation::class);
        $app->singleton(TenantContextResolver::class, static fn (Application $app): TenantContextResolver => ConfiguredImplementation::resolve(app: $app, contract: TenantContextResolver::class, implementation: $app->make(CoreOptions::class)->tenantContextResolver));
        $app->scoped(
            TenantContextRestorer::class,
            static fn (Application $app): GuardedTenantContextRestorer => new GuardedTenantContextRestorer(
                resolver: $app->make(TenantContextResolver::class),
            ),
        );
        $app->singleton(TenancyManager::class, static fn (Application $app): TenancyManager => ConfiguredImplementation::resolve(app: $app, contract: TenancyManager::class, implementation: $app->make(CoreOptions::class)->tenancyManager));
        $app->singleton(ImpersonationService::class, static fn (Application $app): ImpersonationService => ConfiguredImplementation::resolve(app: $app, contract: ImpersonationService::class, implementation: $app->make(CoreOptions::class)->impersonationService));
    }

    #[Override]
    public function boot(Application $app): void {}
}
