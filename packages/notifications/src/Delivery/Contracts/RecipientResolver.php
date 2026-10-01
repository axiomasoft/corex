<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Notifications\Delivery\Data\DeliveryTarget;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Data\NotificationContext;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RecipientResolver
{
    public function resolve(NotificationContext $context, RecipientReference $recipient, string $channel): ?DeliveryTarget;
}
