<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\DomainTrust\Data\ObservedDomainProof;
use CoreX\Tenancy\DomainTrust\Internal\NormalizedHost;

interface DomainOwnershipProofVerifier
{
    public function observe(NormalizedHost $host): ObservedDomainProof;
}
