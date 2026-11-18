<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Contracts;

use CoreX\Scheduling\Data\WakeupDelivery;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void;
}
