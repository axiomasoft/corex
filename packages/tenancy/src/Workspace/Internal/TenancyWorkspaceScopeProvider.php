<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Workspace\Internal;

use CoreX\Context\Laravel\WorkspaceScopeProvider;
use CoreX\Tenancy\Laravel\Scopes\WorkspaceScope;
use Override;

/** @internal tenancy's answer to the kernel's workspace-isolation contract. */
final readonly class TenancyWorkspaceScopeProvider implements WorkspaceScopeProvider
{
    #[Override]
    public function scopeClass(): string
    {
        return WorkspaceScope::class;
    }
}
