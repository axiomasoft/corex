<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Notifications\Delivery\Contracts\DeliveryExecution;
use CoreX\Notifications\Delivery\Internal\DeliveryCoordinator;
use Illuminate\Contracts\Foundation\Application;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DeliveryPortBindings
{
    public static function register(Application $app): void
    {
        $app->bind(DeliveryExecution::class, static fn (Application $app): DeliveryExecution => $app->make(DeliveryCoordinator::class));
    }
}
