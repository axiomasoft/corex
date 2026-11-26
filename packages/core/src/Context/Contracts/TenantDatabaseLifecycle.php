<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\AccountRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Physical DB-per-account drop/export. Lives in
 * axiomasoft/corex so `AccountLifecycle` (core-level FSM consumer) can depend on
 * the seam without importing physical database/S3/`pg_dump` — those belong to the host-selected physical resource owner.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenantDatabaseLifecycle
{
    /** Export through the physical owner; returns the CoreX root_account_exports metadata ID. */
    public function export(AccountRef $account, string $kind): string;

    /**
     * Physically drops the account's tenant database. Implementations MUST
     * refuse (throw) unless the account's most recent export is `ready`
     * (compliance-critical — export-before-DROP invariant).
     */
    public function drop(AccountRef $account): void;
}
