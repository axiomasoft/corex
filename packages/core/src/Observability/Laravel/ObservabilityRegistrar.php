<?php

declare(strict_types=1);

namespace CoreX\Observability\Laravel;

use CoreX\Observability\Contracts\OperationObserver;
use CoreX\Observability\Contracts\TracePropagation;
use CoreX\Observability\Internal\NullOperationObserver;
use CoreX\Observability\Internal\OpenTelemetryBridge;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ObservabilityRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->bind(TracePropagation::class, static fn (Application $app): TracePropagation => $app->make(OpenTelemetryBridge::class));
        $app->instance(OperationObserver::class, new NullOperationObserver);
    }

    #[Override]
    public function boot(Application $app): void {}
}
