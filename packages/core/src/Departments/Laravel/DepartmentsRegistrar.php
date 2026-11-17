<?php

declare(strict_types=1);

namespace CoreX\Departments\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Laravel\ConfiguredImplementation;
use CoreX\Departments\Contracts\DepartmentRepository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DepartmentsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(DepartmentRepository::class, static fn (Application $app): DepartmentRepository => ConfiguredImplementation::resolve(app: $app, contract: DepartmentRepository::class, implementation: $app->make(CoreOptions::class)->departmentRepository));
    }

    #[Override]
    public function boot(Application $app): void {}
}
