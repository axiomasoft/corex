<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Data;

use CoreX\Tenancy\DomainTrust\Enums\DomainTrustChangeReason;
use CoreX\Tenancy\DomainTrust\Internal\NormalizedHost;
use DateTimeImmutable;

final readonly class DomainTrustTransition
{
    public function __construct(
        public string $accountId,
        public NormalizedHost $host,
        public int $oldGeneration,
        public int $newGeneration,
        public DomainTrustChangeReason $reason,
        public DateTimeImmutable $occurredAt,
    ) {}
}
