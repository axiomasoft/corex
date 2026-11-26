<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\MessageEnvelope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Publishes a leased outbox message outside the database transaction.
 * A successful return confirms durable handoff even if the consumer is offline.
 * Implementations must accept redelivery or deduplicate by message ID.
 *
 * Publish the bytes from `CloudEventCodec::encode()` as CloudEvents 1.0 structured
 * JSON with the `application/cloudevents+json` media type. The contract registry
 * selects the channel. See the message policy for the field mapping.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface MessagePublisher
{
    public function publish(MessageEnvelope $message): void;
}
