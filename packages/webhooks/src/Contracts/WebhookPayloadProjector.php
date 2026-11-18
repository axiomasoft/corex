<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Webhooks\Data\WebhookTopic;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Installed field allow-list projector: returns only public data for the external CloudEvent.
 *
 * @phpstan-import-type EventData from CloudEventCodec
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookPayloadProjector
{
    /** @return EventData Projected JSON object; never an envelope or internal metadata. */
    public function project(MessageEnvelope $message, WebhookTopic $topic): array;
}
