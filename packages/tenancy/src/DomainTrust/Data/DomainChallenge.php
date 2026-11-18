<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DomainChallenge
{
    public function __construct(
        public string $accountId,
        public NormalizedHost $host,
        public int $generation,
        public DateTimeImmutable $expiresAt,
    ) {}
}
