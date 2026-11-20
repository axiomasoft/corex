<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookTopic;
use RuntimeException;

final class FixtureWebhookProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        $record = $message->payload->values['record'] ?? null;

        if (! is_string($record)) {
            throw new RuntimeException('The fixture fact requires its declared record ref.');
        }

        return ['record' => $record];
    }
}
