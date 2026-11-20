<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Context\Data\ProviderObservation;
use CoreX\Webhooks\Data\WebhookProviderReference;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface WebhookProviderLookup
{
    /** Report Absent only when the provider can prove an effect on this key never occurred. */
    public function lookup(WebhookProviderReference $effect): ?ProviderObservation;
}
