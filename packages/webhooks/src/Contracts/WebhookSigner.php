<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Webhooks\Data\WebhookSignedHeaders;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
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
