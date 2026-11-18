<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Contracts;

use CoreX\Notifications\Delivery\Data\DeliveryTarget;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RecipientResolver
{
    public function resolve(NotificationContext $context, RecipientReference $recipient, string $channel): ?DeliveryTarget;
}
