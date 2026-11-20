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
    /** @param array<array-key, string> $channels */
    public function __construct(
        public string $requestKey,
        public string $eventType,
        public RecipientReference $recipient,
        public string $templateKey,
        public JsonDocument $payload,
        public ?EntityReference $related,
        public array $channels = ['in_app'],
        public string $source = 'notification.direct',
    ) {
        if ($requestKey === '' || $eventType === '' || $templateKey === '' || strlen($requestKey) > 128 || strlen($eventType) > 128 || strlen($templateKey) > 128) {
            throw new InvalidArgumentException('Invalid notification input.');
        }

        if (preg_match('/\A[a-z][a-z0-9._:-]{0,127}\z/D', $source) !== 1) {
            throw new InvalidArgumentException('Invalid notification producer source.');
        }

        if (! array_is_list($channels) || count($channels) > 20 || count($channels) !== count(array_unique($channels))) {
            throw new InvalidArgumentException('Invalid notification channels.');
        }
        foreach ($channels as $channel) {
            if (preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $channel) !== 1) {
                throw new InvalidArgumentException('Invalid notification channel.');
            }
        }
    }
}
