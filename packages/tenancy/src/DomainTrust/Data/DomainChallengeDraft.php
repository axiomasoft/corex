<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Data;

use CoreX\Tenancy\DomainTrust\Internal\NormalizedHost;
use DateTimeImmutable;

final readonly class DomainChallengeDraft
{
    public function __construct(
        public string $accountId,
        public NormalizedHost $host,
        public int $generation,
        public DateTimeImmutable $expiresAt,
    ) {}
}
