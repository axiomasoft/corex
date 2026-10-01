<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DomainChallengeBinding
{
    public function __construct(
        public string $accountId,
        public NormalizedHost $host,
        public int $generation,
    ) {}
}
