<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Contracts\Foundation\Application;

/**
 * Compatibility entry point for hosts that register the cost services themselves.
 *
 * @deprecated The operations provider registers {@see CostBindingsRegistrar}.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class CostRegistrar
{
    public static function register(Application $app): void
    {
        (new CostBindingsRegistrar)->register($app);
    }
}
