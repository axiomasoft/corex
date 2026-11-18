<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Contracts;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ProvisionParams;
use CoreX\Tenancy\Provisioning\Data\AccountArchive;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Required host adapter to the single physical resource owner (for example tenant-postgres).
 * Methods return only after the owner's committed receipt; pending/unknown outcomes must not
 * be reported as success. The host retains idempotency keys and verifies admission and binding.
 * CoreX passes account identities, never an executable database name or DSN.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccountResources
{
    public function exists(AccountRef $account): bool;

    public function provision(AccountRef $account, ProvisionParams $params): void;

    public function export(AccountRef $account, string $exportId, string $kind): AccountArchive;

    /** Verify the authoritative receipt, current incarnation, content digest, size and account. */
    public function verifyArchive(AccountRef $account, AccountArchive $archive): bool;

    /** Revalidate current incarnation, archive and admission atomically with deletion at the owner. */
    public function drop(AccountRef $account, AccountArchive $archive): void;

    /** Only unclaimed pool resources; the owner must recheck that no consumer owns the resource. */
    public function discardUnclaimed(AccountRef $account): void;
}
