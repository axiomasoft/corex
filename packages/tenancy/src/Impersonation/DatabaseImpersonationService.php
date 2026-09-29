<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation;

use Carbon\CarbonImmutable;
use CoreX\Audit\CurrentActor;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\ImpersonationState;
use CoreX\Tenancy\Account\Models\Account;
use CoreX\Tenancy\Authentication\Exceptions\OidcAuthenticationException;
use CoreX\Tenancy\Impersonation\Events\ImpersonationEnded;
use CoreX\Tenancy\Impersonation\Events\ImpersonationStarted;
use CoreX\Tenancy\Laravel\Support\ImpersonationPolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Override;

/**
 * Cloud implementation of support impersonation (B-11 §5.9, D139). Rebound
 * over the boxed `NullImpersonationService` in `TenancyServiceProvider`
 * under `tenancy.oidc.impersonation.enabled` (D10). `start()`/`stop()`
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
        private readonly ImpersonationPolicy $impersonationPolicy,
        private readonly TenancyOptions $options,
    ) {}

    #[Override]
    public function start(string $grantId, string $staffIdentityId): void
    {
        if ($this->state() !== null) {
            throw OidcAuthenticationException::impersonationDenied();
        }

        $central = $this->centralConnection();

        /** @var Account $account */
        $account = tenancy()->tenant;

        $requireApprovedBy = $this->impersonationPolicy->requireApprovedBy;

        $affected = DB::connection($central)->table('root_impersonation_grants')
            ->where('id', $grantId)
            ->where('staff_identity_id', $staffIdentityId)
            ->where('account_id', $account->id)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->where('reason', '<>', '')
            ->when($requireApprovedBy, static fn ($query) => $query->whereNotNull('approved_by'))
            ->whereExists(static function ($query) use ($staffIdentityId): void {
                $query->selectRaw('1')
                    ->from('root_identities')
                    ->where('id', $staffIdentityId)
                    ->where('is_staff', true);
            })
            ->update(['used_at' => now(), 'updated_at' => now()]);

        if ($affected !== 1) {
            throw OidcAuthenticationException::impersonationDenied();
        }

        $row = DB::connection($central)->table('root_impersonation_grants')->where('id', $grantId)->first();

        $targetUserId = $row->target_user_id ?? $account->owner_identity_id;
        $reason = is_string($row->reason) ? $row->reason : '';

        if (! is_string($targetUserId) || $targetUserId === '' || $reason === '') {
            throw OidcAuthenticationException::loginFailed();
        }

        $this->mirror($account->id, 'impersonation.started', $staffIdentityId, $grantId, $targetUserId, $reason, (string) $row->expires_at);

        $expiresAt = CarbonImmutable::parse($row->expires_at);

        session()->regenerate();
        session([
            self::SESSION_GRANT_ID => $grantId,
            self::SESSION_STAFF_ID => $staffIdentityId,
            self::SESSION_TARGET_ID => $targetUserId,
            self::SESSION_EXPIRES_AT => $expiresAt->toIso8601String(),
            self::SESSION_REASON => $reason,
        ]);

        $this->memoState = new ImpersonationState($grantId, $staffIdentityId, $targetUserId, $expiresAt, $reason);
        $this->resolved = true;

        $authenticated = Auth::guard($this->guard())->loginUsingId($targetUserId);

        if ($authenticated === false) {
            $this->failStart($account->id, $grantId, $staffIdentityId, $targetUserId, $reason);

            throw OidcAuthenticationException::loginFailed();
        }

        event(new ImpersonationStarted($grantId, $staffIdentityId, $targetUserId, $reason));
    }

    #[Override]
    public function stop(): void
    {
        $state = $this->state();

        if ($state === null) {
            return;
        }

        /** @var Account|null $account */
        $account = tenancy()->tenant;
        $accountId = $account?->id;

        $this->clearSession();

        if ($accountId !== null) {
            $this->mirror($accountId, 'impersonation.ended', $state->staffIdentityId, $state->grantId, $state->targetUserId, $state->reason);
        }

        event(new ImpersonationEnded($state->grantId, $state->staffIdentityId, $state->targetUserId, $state->reason));
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

        $grantId = session(self::SESSION_GRANT_ID);
        $staffId = session(self::SESSION_STAFF_ID);
        $targetId = session(self::SESSION_TARGET_ID);
        $expiresAt = session(self::SESSION_EXPIRES_AT);

        if (! is_string($grantId) || ! is_string($staffId) || ! is_string($targetId) || ! is_string($expiresAt)) {
            return $this->memoState = null;
        }

        $reason = session(self::SESSION_REASON);

        if (! is_string($reason) || $reason === '') {
            $reason = $this->claimedReason($grantId);

            if ($reason === null) {
                $this->clearSession();

                return $this->memoState = null;
            }

            session([self::SESSION_REASON => $reason]);
        }

        return $this->memoState = new ImpersonationState($grantId, $staffId, $targetId, CarbonImmutable::parse($expiresAt), $reason);
    }

    private function failStart(string $accountId, string $grantId, string $staffIdentityId, string $targetUserId, string $reason): void
    {
        $this->clearSession();
        $this->mirror($accountId, 'impersonation.failed', $staffIdentityId, $grantId, $targetUserId, $reason);
    }

    private function clearSession(): void
    {
        Auth::guard($this->guard())->logout();
        session()->forget([
            self::SESSION_GRANT_ID,
            self::SESSION_STAFF_ID,
            self::SESSION_TARGET_ID,
            self::SESSION_EXPIRES_AT,
            self::SESSION_REASON,
        ]);
        session()->invalidate();
        session()->regenerateToken();
        $this->memoState = null;
        $this->resolved = true;
    }

    private function claimedReason(string $grantId): ?string
    {
        $reason = DB::connection($this->centralConnection())
            ->table('root_impersonation_grants')
            ->where('id', $grantId)
            ->whereNotNull('used_at')
            ->value('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    private function mirror(string $accountId, string $event, string $staffIdentityId, string $grantId, string $targetUserId, ?string $reason, ?string $expiresAt = null): void
    {
        $payload = ['target_user_id' => $targetUserId, 'reason' => $reason, 'outcome' => $event];

        if ($expiresAt !== null) {
            $payload['expires_at'] = $expiresAt;
        }

        DB::connection($this->centralConnection())->table('root_account_events')->insert([
            'id' => (string) Str::uuid7(),
            'account_id' => $accountId,
            'event' => $event,
            'actor' => json_encode(['type' => 'support', 'id' => $staffIdentityId, 'grant' => $grantId], JSON_THROW_ON_ERROR),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function guard(): string
    {
        return $this->options->oidcGuard;
    }

    private function centralConnection(): string
    {
        return $this->options->centralConnection;
    }
}
