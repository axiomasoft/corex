<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\WebhookTopic;
use CoreX\Messaging\Data\MessageEnvelope;

/**
 * Explicit public export of one declared topic.
 *
 * The service id is resolved from the installed code map. A class or event name
 * from JSON is not a projector.
 */
interface WebhookPayloadProjector
{
    /**
     * @return array{event_id: string, type: string, version: int, occurred_at: string, data: array<string, string>, correlation_id: string|null}
     */
    public function project(MessageEnvelope $message, WebhookTopic $topic): array;
}
