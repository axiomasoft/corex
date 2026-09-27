<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use Closure;
use CoreX\Exceptions\CentralContextException;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use CoreX\Tenancy\WorkspaceRef;

final class ContextFixture implements TenancyManager, TenantContextResolver
{
    public ?TenantContext $value = null;

    public function current(): TenantContext
    {
        return $this->value ?? throw new CentralContextException;
    }

    public function context(): ?TenantContext
    {
        return $this->value;
    }

    public function initialized(): bool
    {
        return $this->value !== null;
    }

    public function initialize(AccountRef $account): void
    {
        $this->value = new TenantContext($account);
    }

    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        $this->value = new TenantContext($this->current()->account, $workspace);
    }

    public function end(): void
    {
        $this->value = null;
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        $previous = $this->value;
        $this->value = new TenantContext($account, $workspace);

        try {
            return $callback();
        } finally {
            $this->value = $previous;
        }
    }
}
