<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Workspace\Internal\Models\Workspace;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CloudWorkScopeDirectory implements WorkScopeDirectory
{
    public function __construct(private AccountRead $accounts, private TenancyOptions $options) {}

    public function account(string $accountId): AccountRef
    {
        return $this->accounts->find(accountId: $accountId, connectionName: $this->options->centralConnection) ?? throw new ScopeContextMismatch('The account is unavailable.');
    }

    public function workspace(string $workspaceId): WorkspaceRef
    {
        $workspace = Workspace::query()->find($workspaceId) ?? throw new ScopeContextMismatch('The workspace is unavailable.');

        return new WorkspaceRef(id: $workspace->id, slug: $workspace->slug, type: $workspace->type, path: $workspace->path, parentId: $workspace->parent_id);
    }
}
