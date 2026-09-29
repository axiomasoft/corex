<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Webhooks\Data\WebhookSignedHeaders;

interface WebhookSigner
{
    /** @param list<string> $secretRefs */
    public function sign(
        string $deliveryId,
        int $timestamp,
        string $rawBody,
        array $secretRefs,
    ): WebhookSignedHeaders;
}
