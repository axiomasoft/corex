<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use CoreX\Notifications\Delivery\Data\RecipientReference;
use InvalidArgumentException;

final readonly class NotificationInput
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $requestKey,
        public string $eventType,
        public RecipientReference $recipient,
        public string $templateKey,
        public array $payload,
        public ?EntityReference $related,
    ) {
        if ($requestKey === '' || $eventType === '' || $templateKey === '' || strlen($requestKey) > 128 || strlen($eventType) > 128 || strlen($templateKey) > 128) {
            throw new InvalidArgumentException('Invalid notification input.');
        }
    }
}
