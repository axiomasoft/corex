<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Context\Data\WorkspaceRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Membership lookup on the already initialized account connection; no authentication or connection switching. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface PrincipalWorkspaceResolver
{
    /** A null slug selects the principal's active default membership. */
    public function resolveFor(string|int $principalId, ?string $slug): WorkspaceRef;
}
