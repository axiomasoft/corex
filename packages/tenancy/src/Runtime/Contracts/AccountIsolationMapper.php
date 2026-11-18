<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Contracts;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Tenant\Data\Context\ContextState;
use Tenant\Data\Context\Selection;

/** Host-owned projection between CoreX accounts and Tenant's verified resource binding. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccountIsolationMapper
{
    /** Resolve trusted binding/route metadata; never construct a selection from request input. */
    public function selection(AccountRef $account, ?WorkspaceRef $workspace = null): Selection;

    /** Pure projection of active, verified runtime state; no I/O or switching. Central/missing returns null. */
    public function context(ContextState $state): ?TenantContext;
}
