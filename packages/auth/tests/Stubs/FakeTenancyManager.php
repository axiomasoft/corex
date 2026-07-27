<?php

declare(strict_types=1);

namespace CoreX\Auth\Tests\Stubs;

use Closure;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\TenantContext;
use CoreX\Tenancy\WorkspaceRef;

/**
 * Test double letting AccessChecker/GrantSource/PermissionLayer tests move
 * the active workspace context on demand. `corex/core`'s own boxed default
 * (NullTenancyManager) makes setWorkspace() a deliberate no-op — it cannot
 * stand in here, corex/auth does not depend on corex/tenancy (AC-28) so
 * there is no real StanclTenancyManager available in this package's tests.
 */
final class FakeTenancyManager implements TenancyManager
{
    private TenantContext $current;

    public function __construct(?WorkspaceRef $workspace = null)
    {
        $this->current = new TenantContext(
            account: new AccountRef(id: 'test-account', slug: 'test', status: 'active', features: [], limits: []),
            workspace: $workspace,
        );
    }

    public function context(): TenantContext
    {
        return $this->current;
    }

    public function initialized(): bool
    {
        return true;
    }

    public function initialize(AccountRef $account): void
    {
        // Unused by these tests.
    }

    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        $this->current = new TenantContext($this->current->account, $workspace);
    }

    public function end(): void
    {
        // Unused by these tests.
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        return $callback();
    }
}
