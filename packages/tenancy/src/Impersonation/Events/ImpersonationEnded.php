<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Events\DomainEvent;
use Override;

/**
 * Fired after {@see CoreX\Tenancy\Impersonation\Internal\DatabaseImpersonationService::stop()}
 * has ended the session and mirrored `root_account_events` (B-11 §3.4) —
 * on explicit `stop()`, TTL expiry, or an externally-set `revoked_at`.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ImpersonationEnded extends DomainEvent
{
    public function __construct(
        public readonly string $grantId,
        public readonly string $staffIdentityId,
        public readonly string $targetUserId,
        public readonly ?string $reason = null,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'auth.impersonation.ended';
    }
}
