<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Contracts\Foundation\Application;

/**
 * Compatibility entry point for hosts that register the saved-view bindings themselves.
 *
 * @deprecated The panels provider registers {@see SavedViewsBindingsRegistrar}.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class SavedViewsRegistrar
{
    public static function register(Application $app): void
    {
        (new SavedViewsBindingsRegistrar)->register($app);
    }
}
