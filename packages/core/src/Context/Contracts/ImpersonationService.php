<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\NullImpersonationService;

/**
 * Support impersonation (B-11 §5.9, AC-31). Boxed installs bind
 * {@see NullImpersonationService} (no grants exist there —
 * B-11 §6.1); cloud installs rebind to `DatabaseImpersonationService`
 * (axiomasoft/corex-tenancy, D10). Lives in axiomasoft/corex (D139). Its only consumer
 * is in Audit and must not import axiomasoft/corex-tenancy.
 *
 * Signature extends B-11 §3.3's snippet (D140): `start()` also takes the
 * AUTHENTICATED staff identity (a leaked `grantId` alone cannot be tied to
 * its presenter), and `state()` is additive — `active()` keeps its exact
 * original return type for spec backward-compatibility.
 *
 * @internal spec: B-11 §3.3/§5.9, D139/D140
 */
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
