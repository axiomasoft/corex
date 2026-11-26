<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use Packman\Attributes\Internal;

/**
 * Boxed-install default: a single constant account context, no tenant
 * bootstrap, `initialized()` always true. Cloud installs (`axiomasoft/corex-tenancy`
 * present) rebind {@see TenancyManager} to its own database engine — same
 * pattern as the module-activation-gate boxed/cloud seam.
 *
 * @internal
 */
#[Internal]
final readonly class NullTenancyManager implements TenancyManager
{
    /** @param Closure():TenantContextResolver $resolver */
    public function __construct(
        private Closure $resolver,
    ) {}

    public function context(): TenantContext
    {
        return ($this->resolver)()->current();
    }

    public function initialized(): bool
    {
        return true;
    }

    public function initialize(AccountRef $account): void
    {
        // no-op — boxed installs run a single constant account.
    }

    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        // no-op — boxed installs have no workspace tier.
    }

    public function end(): void
    {
        // no-op.
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        return $callback();
    }
}
