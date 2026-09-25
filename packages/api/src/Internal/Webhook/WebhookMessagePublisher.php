<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Webhook;

use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Data\MessageEnvelope;
use Override;

/** Durable webhook acceptance, then the existing publisher. Lossy bindings are not an acknowledgement. */
final readonly class WebhookMessagePublisher implements DurableMessagePublisher
{
    public function __construct(
        private DurableMessagePublisher $forward,
        private WebhookFanout $fanout,
    ) {}

    #[Override]
    public function publish(MessageEnvelope $message): void
    {
        $this->fanout->accept($message);
        $this->forward->publish($message);
    }
}
