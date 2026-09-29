<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Identity\Events;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Messaging\Events\DomainEvent;
use Override;

/**
 * Fired from inside the target account's tenant context (B-11 §3.2) — the
 * ambient {@see TenantContextResolver} resolves the
 * account this projection was synced into.
 */
final class UserSynced extends DomainEvent
{
    public function __construct(
        public readonly string $identityId,
        public readonly string $accountId,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'tenancy.user.synced';
    }
}
