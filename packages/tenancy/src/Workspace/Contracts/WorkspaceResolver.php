<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Workspace\Contracts;

use CoreX\Context\Data\WorkspaceRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Resolves `/w/{slug}` to a workspace, checking the current user's
 * membership (B-11 §3.2). A null slug resolves to the user's default
 * workspace.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WorkspaceResolver
{
    /**
     * Null-slug → default workspace of the current user. No active
     * membership on the target workspace → 403.
     */
    public function resolve(?string $slug): WorkspaceRef;
}
