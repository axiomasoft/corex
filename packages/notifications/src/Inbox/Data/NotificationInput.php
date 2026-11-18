<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use CoreX\Data\Data\JsonDocument;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class NotificationInput
{
    /** Immutable JSON value. */
    public function __construct(
        public string $requestKey,
        public string $eventType,
        public RecipientReference $recipient,
        public string $templateKey,
        public JsonDocument $payload,
        public ?EntityReference $related,
    ) {
        if ($requestKey === '' || $eventType === '' || $templateKey === '' || strlen($requestKey) > 128 || strlen($eventType) > 128 || strlen($templateKey) > 128) {
            throw new InvalidArgumentException('Invalid notification input.');
        }
    }
}
