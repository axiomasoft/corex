<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Stubs;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;

/**
 * Test double letting AccessChecker/GrantSource/PermissionLayer tests move
 * the active workspace context on demand. `axiomasoft/corex`'s own boxed default
 * (NullTenancyManager) makes setWorkspace() a deliberate no-op — it cannot
 * stand in here, axiomasoft/corex-access does not depend on axiomasoft/corex-tenancy (AC-28) so
 * there is no real DatabaseTenancyManager available in this package's tests.
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
