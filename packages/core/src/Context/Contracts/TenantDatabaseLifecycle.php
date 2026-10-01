<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\AccountRef;

/**
 * Physical DB-per-account drop/export (B-11 §5.1/§7.3, D13/AC-7). Lives in
 * axiomasoft/corex so `AccountLifecycle` (core-level FSM consumer) can depend on
 * the seam without importing `Stancl\*`/S3/`pg_dump` — those stay isolated
 * to the axiomasoft/corex-tenancy implementation (D13).
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenantDatabaseLifecycle
{
    /** pg_dump -Fc + CSV/JSON per entity to S3; returns id of root_account_exports row. */
    public function export(AccountRef $account, string $kind): string;

    /**
     * Physically drops the account's tenant database. Implementations MUST
     * refuse (throw) unless the account's most recent export is `ready`
     * (D13/AC-7, compliance-critical — export-before-DROP invariant).
     */
    public function drop(AccountRef $account): void;
}
