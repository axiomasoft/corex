<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use Illuminate\Container\Container;
use Illuminate\Database\DatabaseManager;

/** Provisioned single-account host with database-backed workspace IDs and a fixed owner connection. */
final class BoxedWorkspaces implements TenancyManager, TenantContextResolver, WorkScopeDirectory
{
    private ?WorkspaceRef $active = null;

    public function __construct(private readonly Container $container, private readonly DatabaseManager $database) {}

    public function current(): TenantContext
    {
        return new TenantContext($this->account(Scenario::BOXED), $this->active, boxed: true);
    }

    public function context(): TenantContext
    {
        return $this->current();
    }

    public function initialized(): bool
    {
        return true;
    }

    public function initialize(AccountRef $account): void
    {
        $this->account($account->id);
        $this->setWorkspace(null);
    }

    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        $this->active = $workspace === null ? null : $this->workspace($workspace->id);
        $this->container->forgetScopedInstances();
    }

    public function end(): void
    {
        $this->setWorkspace(null);
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        $this->account($account->id);
        $outer = $this->active;
        $this->setWorkspace($workspace);

        try {
            return $callback();
        } finally {
            $this->active = $outer;
            $this->container->forgetScopedInstances();
        }
    }

    public function account(string $accountId): AccountRef
    {
        if ($accountId !== Scenario::BOXED) {
            throw new ScopeContextMismatch('The host owns a single account.');
        }

        return Scenario::account($accountId);
    }

    public function workspace(string $workspaceId): WorkspaceRef
    {
        $row = $this->database->connection()->table('wsp_workspaces')->where('id', $workspaceId)->whereNull('deleted_at')->first();

        if ($row === null) {
            throw new ScopeContextMismatch('The boxed host has no such workspace.');
        }

        return new WorkspaceRef($row->id, $row->slug, $row->type, $row->path, null);
    }
}
