<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Data;

use CoreX\Tenancy\Impersonation\Enums\ImpersonationProfile;
use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class GrantClaim
{
    public function __construct(
        public string $grantId,
        public string $staffIdentityId,
        public string $accountId,
        public ImpersonationProfile $profile,
        public DateTimeImmutable $now,
    ) {}
}
