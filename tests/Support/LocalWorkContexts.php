<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use Illuminate\Container\Container;

final class LocalWorkContexts implements TenancyManager, TenantContextResolver, WorkScopeDirectory
{
    private ?TenantContext $current = null;

    /** @var array<string,AccountRef> */
    private array $accounts = [];

    /** @var array<string,WorkspaceRef> */
    private array $workspaces = [];

    public function __construct(private readonly Container $app) {}

    public static function install(Container $app): self
    {
        $host = new self($app);
        $app->singleton(TenancyManager::class, static fn () => $host);
        $app->singleton(TenantContextResolver::class, static fn () => $host);
        $app->singleton(WorkScopeDirectory::class, static fn () => $host);

        return $host;
    }

    public function current(): TenantContext
    {
        return $this->current ?? throw new CentralContextException;
    }

    public function context(): ?TenantContext
    {
        return $this->current;
    }

    public function account(string $accountId): AccountRef
    {
        return $this->accounts[$accountId] ?? throw new ScopeContextMismatch('Unknown account.');
    }

    public function workspace(string $workspaceId): WorkspaceRef
    {
        return $this->workspaces[$this->current()->account->id.':'.$workspaceId] ?? throw new ScopeContextMismatch('Unknown workspace.');
    }

    public function initialized(): bool
    {
        return $this->current !== null;
    }

    public function initialize(AccountRef $account): void
    {
        $this->accounts[$account->id] = $account;
        $this->current = new TenantContext($account);
        $this->app->forgetScopedInstances();
    }

    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        $account = $this->current()->account;

        if ($workspace !== null) {
            $this->workspaces[$account->id.':'.$workspace->id] = $workspace;
        }
        $this->current = new TenantContext($account, $workspace);
        $this->app->forgetScopedInstances();
    }

    public function end(): void
    {
        $this->current = null;
        $this->app->forgetScopedInstances();
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        $previous = $this->current;

        try {
            $this->initialize($account);
            $this->setWorkspace($workspace);

            return $callback();
        } finally {
            $this->current = $previous;
            $this->app->forgetScopedInstances();
        }
    }
}
