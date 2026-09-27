<?php

declare(strict_types=1);

namespace CoreX\Notifications\Contracts;

use CoreX\Notifications\Data\DeliveryTarget;
use CoreX\Notifications\Data\NotificationContext;
use CoreX\Notifications\Data\RecipientReference;

interface RecipientResolver
{
    public function resolve(NotificationContext $context, RecipientReference $recipient, string $channel): ?DeliveryTarget;
}
