<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\ImpersonationState;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Support impersonation. Boxed installs bind
 * {@see CoreX\Context\Internal\NullImpersonationService} because boxed installs have no grants. Cloud installs rebind to `DatabaseImpersonationService`
 * (axiomasoft/corex-tenancy). Lives in axiomasoft/corex. Its only consumer
 * is in Audit and must not import axiomasoft/corex-tenancy.
 *
 * `start()` takes the authenticated staff identity (a leaked `grantId` alone cannot be tied to
 * its presenter), and `state()` is additive — `active()` keeps its exact
 * original return type for backward compatibility.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ImpersonationService
{
    /**
     * Claims and activates `$grantId` on behalf of `$staffIdentityId`.
     * Implementations MUST reject reuse, expiry, revocation, non-staff
     * presenters, and grants issued for a different account/tenant — see
     * `DatabaseImpersonationService` for the exact predicate.
     */
    public function start(string $grantId, string $staffIdentityId): void;

    /** Idempotent — a no-op when no impersonation is active. */
    public function stop(): void;

    /** The active grant id, or null outside impersonation. */
    public function active(): ?string;

    /** The active impersonation snapshot, or null outside impersonation. */
    public function state(): ?ImpersonationState;
}
