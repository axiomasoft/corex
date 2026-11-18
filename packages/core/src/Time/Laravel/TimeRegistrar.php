<?php

declare(strict_types=1);

namespace CoreX\Time\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\DateFactory;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TimeRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(ClockInterface::class, static fn (Application $app): LaravelClock => new LaravelClock(
            dates: $app->make(DateFactory::class),
        ));
    }

    #[Override]
    public function boot(Application $app): void {}
}
