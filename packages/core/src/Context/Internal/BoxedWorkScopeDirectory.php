<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use Packman\Attributes\Internal;

#[Internal]
final readonly class BoxedWorkScopeDirectory implements WorkScopeDirectory
{
    public function __construct(private TenantContextResolver $contexts) {}

    public function account(string $accountId): AccountRef
    {
        $account = $this->contexts->current()->account;

        return $account->id === $accountId ? $account : throw new ScopeContextMismatch('Unknown boxed account.');
    }

    public function workspace(string $workspaceId): WorkspaceRef
    {
        $workspace = $this->contexts->current()->workspace;

        return $workspace?->id === $workspaceId ? $workspace : throw new ScopeContextMismatch('Unknown boxed workspace.');
    }
}
