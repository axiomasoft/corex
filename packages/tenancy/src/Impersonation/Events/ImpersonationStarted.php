<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Events;

use CoreX\Messaging\Events\DomainEvent;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Fired after {@see CoreX\Tenancy\Impersonation\Internal\DatabaseImpersonationService::start()}
 * has claimed the grant, mirrored `root_account_events`, and opened the
 * tenant session (B-11 §3.4).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ImpersonationStarted extends DomainEvent
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
