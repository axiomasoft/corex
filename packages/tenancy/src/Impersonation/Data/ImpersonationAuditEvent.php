<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Data;

use CoreX\Attributes\Api;
use CoreX\Tenancy\Impersonation\Enums\ImpersonationProfile;
use DateTimeImmutable;

#[Api(since: '1.0')]
final readonly class ImpersonationAuditEvent
{
    public function __construct(
        public string $event,
        public string $accountId,
        public string $grantId,
        public string $staffIdentityId,
        public string $targetUserId,
        public string $reason,
        public ImpersonationProfile $profile,
        public DateTimeImmutable $occurredAt,
        public ?DateTimeImmutable $expiresAt = null,
    ) {}
}
