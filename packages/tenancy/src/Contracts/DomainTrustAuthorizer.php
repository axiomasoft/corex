<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\DomainTrust\Data\DomainTrustActor;
use CoreX\Tenancy\DomainTrust\Data\NormalizedHost;
use CoreX\Tenancy\DomainTrust\Enums\DomainTrustOperation;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DomainTrustAuthorizer
{
    public function assertAllowed(
        DomainTrustActor $actor,
        DomainTrustOperation $operation,
        string $accountId,
        NormalizedHost $host,
    ): void;
}
