<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Enums\ImpersonationRestrictedAction;
use CoreX\Context\Exceptions\ImpersonationRestrictedException;
use Packman\Attributes\Internal;

/**
 * Semantic layer of the read-only invariant: the assert point
 * for privileged core services that reach neither L1 (not an HTTP request)
 * nor L2 (not an Eloquent model event), e.g. `DROP DATABASE` in
 * `AccountLifecycle::purge()`. `final`, not an interface — its
 * verdict is entirely derived from {@see ImpersonationService::state()}, a
 * second implementation would be a fabricated seam.
 *
 * @internal
 */
#[Internal]
final readonly class ImpersonationGuard implements \CoreX\Context\Contracts\ImpersonationGuard
{
    public function __construct(
        private ImpersonationService $impersonation,
    ) {}

    public function assertAllowed(ImpersonationRestrictedAction $action): void
    {
        if ($this->denies($action)) {
            throw ImpersonationRestrictedException::forAction($action);
        }
    }

    public function denies(ImpersonationRestrictedAction $action): bool
    {
        return $this->impersonation->state() !== null;
    }
}
