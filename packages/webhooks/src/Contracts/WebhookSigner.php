<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Webhooks\Data\WebhookSignedHeaders;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

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
