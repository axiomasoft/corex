<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Tests\Support;

use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupDelivery;

/** Minimal handler used where only its class name matters. */
final class OptionsProbeHandler implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void {}
}
