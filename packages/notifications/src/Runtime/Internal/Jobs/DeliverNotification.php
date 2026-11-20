<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal\Jobs;

use CoreX\Notifications\Runtime\Internal\DeliveryCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packman\Attributes\Internal;

#[Internal]
final class DeliverNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(DeliveryCoordinator $coordinator): void
    {
        $coordinator->deliver($this->deliveryId);
    }
}
