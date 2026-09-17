<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Wakeup\Data\WakeupDelivery;

interface WakeupHandler
{
    public function handle(WakeupDelivery $delivery): void;
}
