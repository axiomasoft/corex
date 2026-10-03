<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Contracts\Foundation\Application;

/**
 * Compatibility entry point for hosts that register the bulk operation services themselves.
 *
 * @deprecated The operations provider registers {@see BulkBindingsRegistrar}.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class BulkRegistrar
{
    public static function register(Application $app): void
    {
        (new BulkBindingsRegistrar)->register($app);
    }

    public static function boot(Application $app): void
    {
        (new BulkHttpRegistrar)->boot($app);
    }
}
