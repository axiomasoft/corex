<?php

declare(strict_types=1);

namespace CoreX\Config\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Internal\KernelConfigSection;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ConfigRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(CoreOptionsParser::class);
        $app->tag([KernelConfigSection::class], ConfigReportSection::class);
        $app->singleton(CoreOptions::class, static fn (Application $app): CoreOptions => CoreOptionsParser::fromArray(
            (array) $app->make(Repository::class)->get('corex', []),
        ));
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(CoreOptions::class);
        RuntimeConfigGate::install($app);
    }
}
