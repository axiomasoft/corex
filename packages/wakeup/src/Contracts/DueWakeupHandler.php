<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Wakeup\Data\WakeupDelivery;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void;
}
