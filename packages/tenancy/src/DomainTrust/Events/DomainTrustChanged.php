<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Events;

use CoreX\Tenancy\DomainTrust\Data\DomainTrustTransition;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class DomainTrustChanged
{
    public function __construct(public readonly DomainTrustTransition $transition) {}
}
