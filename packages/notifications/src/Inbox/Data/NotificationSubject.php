<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use CoreX\Notifications\Delivery\Data\RecipientReference;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class NotificationSubject
{
    public function __construct(public ?string $notificationId, public RecipientReference $recipient, public ?EntityReference $related) {}
}
