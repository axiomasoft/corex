<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Contracts;

use CoreX\Notifications\Delivery\Data\ChannelDescriptor;
use CoreX\Notifications\Delivery\Data\DeliveryInput;
use CoreX\Notifications\Delivery\Data\DeliveryOutcome;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface NotificationChannelDriver
{
    public function descriptor(): ChannelDescriptor;

    public function send(DeliveryInput $input): DeliveryOutcome;

    public function reconcile(DeliveryInput $input): DeliveryOutcome;
}
