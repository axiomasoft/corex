<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Events;

use CoreX\Messaging\Events\DomainEvent;
use CoreX\Tenancy\Impersonation\DatabaseImpersonationService;
use Override;

/**
 * Fired after {@see DatabaseImpersonationService::start()}
 * has claimed the grant, mirrored `root_account_events`, and opened the
 * tenant session (B-11 §3.4).
 */
final class ImpersonationStarted extends DomainEvent
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
        return 'auth.impersonation.started';
    }
}
