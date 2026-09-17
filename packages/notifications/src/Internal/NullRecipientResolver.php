<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use CoreX\Notifications\Contracts\RecipientResolver;
use CoreX\Notifications\Data\DeliveryTarget;
use CoreX\Notifications\Data\NotificationContext;
use CoreX\Notifications\Data\RecipientReference;

final class NullRecipientResolver implements RecipientResolver
{
    public function resolve(NotificationContext $context, RecipientReference $recipient, string $channel): ?DeliveryTarget
    {
        return $channel === 'in_app'
            ? new DeliveryTarget(channel: 'in_app', reference: $recipient->id)
            : null;
    }
}
