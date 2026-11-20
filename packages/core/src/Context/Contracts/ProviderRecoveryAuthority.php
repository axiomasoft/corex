<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\ProviderEffectRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ProviderRecoveryAuthority
{
    /** Validate the independently authenticated system or operator credential for this exact effect. */
    public function authorize(ProviderEffectRef $effect): void;
}
