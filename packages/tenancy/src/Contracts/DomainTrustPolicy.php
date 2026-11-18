<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\DomainTrust\Data\NormalizedHost;
use CoreX\Tenancy\DomainTrust\Data\ObservedDomainProof;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DomainTrustPolicy
{
    public function normalize(string $host): NormalizedHost;

    public function accepts(NormalizedHost $host, ObservedDomainProof $proof): bool;
}
