<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Internal;

use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Authentication\Exceptions\OidcAuthenticationException;
use CoreX\Tenancy\Impersonation\Contracts\ImpersonationAuditSink;
use CoreX\Tenancy\Impersonation\Contracts\ImpersonationGrantStore;
use CoreX\Tenancy\Impersonation\Data\GrantClaim;
use CoreX\Tenancy\Impersonation\Data\ImpersonationAuditEvent;
use CoreX\Tenancy\Impersonation\Events\ImpersonationEnded;
use CoreX\Tenancy\Impersonation\Events\ImpersonationStarted;
use CoreX\Tenancy\Laravel\Support\ImpersonationPolicy;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Override;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Cloud implementation of support impersonation (B-11 §5.9, D139). Rebound
 * over the boxed `NullImpersonationService` in `TenancyServiceProvider`
 * under `corex-tenancy.oidc.impersonation.enabled` (D10). `start()`/`stop()`
 * follow research/12 §4 EXACTLY — atomic claim → dual-log mirror BEFORE the
 * session opens (D146) → session state + login; `stop()` reverses the
 * mirror order (session first) because the tenant session, not the audit
 * mirror, is the safety-critical half of the pair.
 *
 * @internal spec: B-11 §5.9, D139/D140/D143/D144/D146
 */
final class DatabaseImpersonationService implements ImpersonationService
{
    private const string SESSION_GRANT_ID = 'corex.impersonation.grant_id';

    private const string SESSION_STAFF_ID = 'corex.impersonation.staff_identity_id';

    private const string SESSION_TARGET_ID = 'corex.impersonation.target_user_id';

    private const string SESSION_EXPIRES_AT = 'corex.impersonation.expires_at';

    private const string SESSION_REASON = 'corex.impersonation.reason';

    /**
     * Request-scoped memo (D139 docblock) — this service is bound as a
     * scoped service, so the container gives one instance per request; `state()`
     * reads the session ONCE and caches the result (or its absence) instead
     * of re-parsing on every {@see CurrentActor} call.
     */
    private bool $resolved = false;

    private ?ImpersonationState $memoState = null;

    public function __construct(
        private ImpersonationPolicy $policy,
        private ImpersonationGrantStore $grants,
        private ImpersonationAuditSink $audit,
        private AccountRead $accounts,
        private TenantContextResolver $contexts,
        private Session $session,
        private AuthFactory $auth,
        private Dispatcher $events,
        private ClockInterface $clock,
        private string $guard,
        private string $centralConnection,
    ) {}

    #[Override]
    public function start(string $grantId, string $staffIdentityId): void
    {
        if (! $this->policy->enabled || $this->state() !== null) {
            throw OidcAuthenticationException::impersonationDenied();
        }

        $accountId = $this->contexts->current()->account->id;
        $claimed = $this->grants->claim(new GrantClaim(
            grantId: $grantId,
            staffIdentityId: $staffIdentityId,
            accountId: $accountId,
            profile: $this->policy->profile,
            now: $this->clock->now(),
        ));

        if ($claimed === null) {
            throw OidcAuthenticationException::impersonationDenied();
        }

        $targetUserId = $claimed->targetUserId ?? $this->accounts->ownerIdentityId($accountId, $this->centralConnection);

        if (! is_string($targetUserId) || $targetUserId === '') {
            throw OidcAuthenticationException::loginFailed();
        }

        $this->record(event: 'impersonation.started', accountId: $accountId, staffIdentityId: $staffIdentityId, grantId: $grantId, targetUserId: $targetUserId, reason: $claimed->reason, expiresAt: $claimed->expiresAt);

        $this->session->regenerate();
        $this->session->put([
            self::SESSION_GRANT_ID => $grantId,
            self::SESSION_STAFF_ID => $staffIdentityId,
            self::SESSION_TARGET_ID => $targetUserId,
            self::SESSION_EXPIRES_AT => $claimed->expiresAt->format(DATE_ATOM),
            self::SESSION_REASON => $claimed->reason,
        ]);

        $this->memoState = new ImpersonationState($grantId, $staffIdentityId, $targetUserId, $claimed->expiresAt, $claimed->reason);
        $this->resolved = true;

        try {
            $authenticated = $this->statefulGuard()->loginUsingId($targetUserId);
        } catch (Throwable) {
            $this->failStart($accountId, $grantId, $staffIdentityId, $targetUserId, $claimed->reason, $claimed->expiresAt);

            throw OidcAuthenticationException::loginFailed();
        }

        if ($authenticated === false) {
            $this->failStart($accountId, $grantId, $staffIdentityId, $targetUserId, $claimed->reason, $claimed->expiresAt);

            throw OidcAuthenticationException::loginFailed();
        }

        $this->events->dispatch(new ImpersonationStarted($grantId, $staffIdentityId, $targetUserId, $claimed->reason));
    }

    #[Override]
    public function stop(): void
    {
        $state = $this->state();

        if ($state === null) {
            return;
        }

        try {
            $accountId = $this->contexts->current()->account->id;
        } catch (CentralContextException) {
            $accountId = null;
        }

        $this->clearSession();

        if ($accountId !== null) {
            $this->record(event: 'impersonation.ended', accountId: $accountId, staffIdentityId: $state->staffIdentityId, grantId: $state->grantId, targetUserId: $state->targetUserId, reason: (string) $state->reason, expiresAt: $state->expiresAt);
        }

        $this->events->dispatch(new ImpersonationEnded($state->grantId, $state->staffIdentityId, $state->targetUserId, (string) $state->reason));
    }

    #[Override]
    public function active(): ?string
    {
        return $this->state()?->grantId;
    }

    #[Override]
    public function state(): ?ImpersonationState
    {
        if ($this->resolved) {
            return $this->memoState;
        }

        $this->resolved = true;

        $grantId = $this->session->get(self::SESSION_GRANT_ID);
        $staffId = $this->session->get(self::SESSION_STAFF_ID);
        $targetId = $this->session->get(self::SESSION_TARGET_ID);
        $expiresAt = $this->session->get(self::SESSION_EXPIRES_AT);

        if (! is_string($grantId) || ! is_string($staffId) || ! is_string($targetId) || ! is_string($expiresAt)) {
            return $this->memoState = null;
        }

        $reason = $this->session->get(self::SESSION_REASON);

        if (! is_string($reason) || $reason === '') {
            $reason = $this->grants->claimedReason($grantId);

            if ($reason === null) {
                $this->clearSession();

                return $this->memoState = null;
            }

            $this->session->put(self::SESSION_REASON, $reason);
        }

        return $this->memoState = new ImpersonationState($grantId, $staffId, $targetId, new DateTimeImmutable($expiresAt), $reason);
    }

    private function failStart(string $accountId, string $grantId, string $staffIdentityId, string $targetUserId, string $reason, DateTimeImmutable $expiresAt): void
    {
        $this->clearSession();
        $this->record(event: 'impersonation.failed', accountId: $accountId, staffIdentityId: $staffIdentityId, grantId: $grantId, targetUserId: $targetUserId, reason: $reason, expiresAt: $expiresAt);
    }

    private function clearSession(): void
    {
        $this->statefulGuard()->logout();
        $this->session->forget([
            self::SESSION_GRANT_ID,
            self::SESSION_STAFF_ID,
            self::SESSION_TARGET_ID,
            self::SESSION_EXPIRES_AT,
            self::SESSION_REASON,
        ]);
        $this->session->invalidate();
        $this->session->regenerateToken();
        $this->memoState = null;
        $this->resolved = true;
    }

    private function record(string $event, string $accountId, string $staffIdentityId, string $grantId, string $targetUserId, string $reason, DateTimeImmutable $expiresAt): void
    {
        $this->audit->record(new ImpersonationAuditEvent(
            event: $event,
            accountId: $accountId,
            grantId: $grantId,
            staffIdentityId: $staffIdentityId,
            targetUserId: $targetUserId,
            reason: $reason,
            profile: $this->policy->profile,
            occurredAt: $this->clock->now(),
            expiresAt: $expiresAt,
        ));
    }

    private function statefulGuard(): StatefulGuard
    {
        $guard = $this->auth->guard($this->guard);

        if (! $guard instanceof StatefulGuard) {
            throw OidcAuthenticationException::loginFailed();
        }

        return $guard;
    }
}
