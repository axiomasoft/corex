<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ProviderReceiptProjector
{
    public function owner(): string;

    public function lookup(ProviderEffectStatus $status): ?ProviderObservation;

    /** Commit the owner's receipt or quarantine a conflicting ticket. Return null while unresolved. */
    public function project(ProviderEffectStatus $status): ?string;
}
