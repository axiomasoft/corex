<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Contracts\Foundation\Application;

/**
 * Compatibility entry point for hosts that register the webhook services themselves.
 *
 * @deprecated The webhooks provider registers {@see WebhookBindingsRegistrar}.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class WebhookRegistrar
{
    public static function register(Application $app): void
    {
        (new WebhookBindingsRegistrar)->register($app);
    }

    public static function boot(Application $app): void
    {
        (new WebhookBindingsRegistrar)->boot($app);
    }
}
