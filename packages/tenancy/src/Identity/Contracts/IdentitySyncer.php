<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Identity\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Keeps an account's tenant-DB `users` projection in
 * step with the central `root_identities` row (full denormalization).
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface IdentitySyncer
{
    /** Idempotent upsert of the users-row for one account. */
    public function syncToAccount(string $identityId, string $accountId): void;

    /** Queued fan-out across every account membership (root_identity_accounts). */
    public function fanOut(string $identityId): void;
}
