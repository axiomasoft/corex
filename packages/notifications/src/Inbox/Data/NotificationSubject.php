<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use CoreX\Attributes\Api;
use CoreX\Notifications\Delivery\Data\RecipientReference;

#[Api(since: '1.0')]
final readonly class NotificationSubject
{
    public function __construct(public ?string $notificationId, public RecipientReference $recipient, public ?EntityReference $related) {}
}
