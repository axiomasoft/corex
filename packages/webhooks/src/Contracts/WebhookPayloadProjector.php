<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Webhooks\Data\WebhookTopic;

/**
 * Explicit public export of one declared topic.
 *
 * The service id is resolved from the installed code map. A class or event name
 * from JSON is not a projector.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookPayloadProjector
{
    /**
     * @return array{event_id: string, type: string, version: int, occurred_at: string, data: array<string, string>, correlation_id: string|null}
     */
    public function project(MessageEnvelope $message, WebhookTopic $topic): array;
}
