<?php

declare(strict_types=1);

namespace CoreX\Modules\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\ModuleRegistrar;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Internal\ServiceProviderModuleRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CoreModulesRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(ModuleRegistrar::class, ServiceProviderModuleRegistrar::class);
    }

    #[Override]
    public function boot(Application $app): void {}
}
