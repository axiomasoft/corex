<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Workspace\Internal;

use CoreX\Context\Laravel\WorkspaceScopeProvider;
use CoreX\Tenancy\Laravel\Scopes\WorkspaceScope;
use Override;
use Packman\Attributes\Internal;

/** @internal tenancy's answer to the kernel's workspace-isolation contract. */
#[Internal]
final readonly class TenancyWorkspaceScopeProvider implements WorkspaceScopeProvider
{
    #[Override]
    public function scopeClass(): string
    {
        return WorkspaceScope::class;
    }
}
