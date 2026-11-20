<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Notifications\Delivery\Contracts\RecipientResolver;
use CoreX\Notifications\Delivery\Data\DeliveryTarget;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use Packman\Attributes\Internal;

#[Internal]
final class NullRecipientResolver implements RecipientResolver
{
    public function resolve(NotificationContext $context, RecipientReference $recipient, string $channel): ?DeliveryTarget
    {
        return $channel === 'in_app'
            ? new DeliveryTarget(channel: 'in_app', reference: $recipient->id)
            : null;
    }
}
