<?php

declare(strict_types=1);

namespace CoreX\Audit\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Contracts\AuditRetention;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Laravel\ConfiguredImplementation;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AuditRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(AuditLogger::class, static fn (Application $app): AuditLogger => ConfiguredImplementation::resolve($app, AuditLogger::class, $app->make(CoreOptions::class)->auditLogger));
        $app->singleton(AuditRetention::class, static fn (Application $app): AuditRetention => ConfiguredImplementation::resolve($app, AuditRetention::class, $app->make(CoreOptions::class)->auditRetention));
    }

    #[Override]
    public function boot(Application $app): void {}
}
