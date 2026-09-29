<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\DomainTrust\Data\ObservedDomainProof;
use CoreX\Tenancy\DomainTrust\Internal\NormalizedHost;

interface DomainTrustPolicy
{
    public function normalize(string $host): NormalizedHost;

    public function accepts(NormalizedHost $host, ObservedDomainProof $proof): bool;
}
