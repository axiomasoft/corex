<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

/**
 * Snapshot of an active impersonation session — resolved from the claimed
 * `root_impersonation_grants` row (D143), never re-derived from the id_token.
 */
#[Api(since: '1.0')]
final readonly class ImpersonationState
{
    public function __construct(
        public string $grantId,
        public string $staffIdentityId,
        public string $targetUserId,
        public DateTimeImmutable $expiresAt,
        public ?string $reason = null,
    ) {}
}
