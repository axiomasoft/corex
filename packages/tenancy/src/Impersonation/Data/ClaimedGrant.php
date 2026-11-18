<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ClaimedGrant
{
    public function __construct(
        public string $grantId,
        public ?string $targetUserId,
        public string $reason,
        public DateTimeImmutable $expiresAt,
    ) {}
}
