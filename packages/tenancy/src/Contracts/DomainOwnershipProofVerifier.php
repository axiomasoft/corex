<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\DomainTrust\Data\NormalizedHost;
use CoreX\Tenancy\DomainTrust\Data\ObservedDomainProof;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DomainOwnershipProofVerifier
{
    public function observe(NormalizedHost $host): ObservedDomainProof;
}
