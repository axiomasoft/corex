<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Tests\Support;

use CoreX\Wakeup\Contracts\DueWakeupHandler;
use CoreX\Wakeup\Data\WakeupDelivery;

/** Minimal handler used where only its class name matters. */
final class OptionsProbeHandler implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void {}
}
