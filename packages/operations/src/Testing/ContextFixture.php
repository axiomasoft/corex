<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\CentralContextException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
        $this->value = new TenantContext(account: $this->current()->account, workspace: $workspace);
    }

    public function end(): void
    {
        $this->value = null;
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        $previous = $this->value;
        $this->value = new TenantContext(account: $account, workspace: $workspace);

        try {
            return $callback();
        } finally {
            $this->value = $previous;
        }
    }
}
