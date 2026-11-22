<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict;
use JsonException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class NotificationVisibility
{
    public function __construct(private NotificationPolicy $policy) {}

    public function allows(NotificationContext $context, NotificationSubject $subject): bool
    {
        return $this->policy->allows(context: $context, action: NotificationAction::Read, subject: $subject);
    }

    public function subject(object $row): NotificationSubject
    {
        return new NotificationSubject(
            notificationId: NotificationValue::text(NotificationValue::field(row: $row, name: 'id')),
            recipient: new RecipientReference(
                kind: NotificationValue::text(NotificationValue::field(row: $row, name: 'recipient_kind')),
                id: NotificationValue::text(NotificationValue::field(row: $row, name: 'recipient_id')),
            ),
            related: $this->related(NotificationValue::field(row: $row, name: 'related')),
        );
    }

    private function related(mixed $value): ?EntityReference
    {
        if ($value === null) {
            return null;
        }

        try {
            $related = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
        } catch (JsonException $exception) {
            throw new NotificationOperationConflict('Notification relation is invalid.', previous: $exception);
        }

        if (! is_array($related) || ! isset($related['handle'], $related['id']) || ! is_string($related['handle']) || ! is_string($related['id'])) {
            throw new NotificationOperationConflict('Notification relation is invalid.');
        }

        return new EntityReference(handle: $related['handle'], id: $related['id']);
    }
}
