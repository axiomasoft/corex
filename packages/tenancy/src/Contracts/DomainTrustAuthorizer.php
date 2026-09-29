<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\DomainTrust\Data\DomainTrustActor;
use CoreX\Tenancy\DomainTrust\Enums\DomainTrustOperation;
use CoreX\Tenancy\DomainTrust\Internal\NormalizedHost;

interface DomainTrustAuthorizer
{
    public function assertAllowed(
        DomainTrustActor $actor,
        DomainTrustOperation $operation,
        string $accountId,
        NormalizedHost $host,
    ): void;
}
