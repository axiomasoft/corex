<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use CoreX\Attributes\Api;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use DateTimeImmutable;

#[Api(since: '1.0')]
final readonly class NotificationRef
{
    public function __construct(
        public string $id,
        public RecipientReference $recipient,
        public string $eventType,
        public string $revision,
        public ?DateTimeImmutable $readAt,
        public ?DateTimeImmutable $cancelledAt,
        public ?EntityReference $related,
    ) {}
}
