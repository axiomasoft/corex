<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Lifecycle;

use CoreX\Audit\CurrentActor;
use CoreX\Enums\ImpersonationRestrictedAction;
use CoreX\Events\DomainEvent;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenantDatabaseLifecycle;
use CoreX\Tenancy\Events\AccountSuspended;
use CoreX\Tenancy\Events\GraceStarted;
use CoreX\Tenancy\Events\TrialStarted;
use CoreX\Tenancy\ImpersonationGuard;
use CoreX\Tenancy\Provisioning\PendingPool;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The account FSM (B-11 §5.1/§5.2, P2.12) — `root_accounts.status` MUST be
 * mutated ONLY through this class. Every transition writes one
 * `root_account_events` row ({@see writeEvent()}); the transitions that own
 * a dedicated {@see DomainEvent} subclass also fire it
 * (`startTrial`→TrialStarted, `suspend`→AccountSuspended,
 * `startGrace`→GraceStarted; `AccountPurged`/`ExportReady` are fired by
 * {@see StanclTenantDatabaseLifecycle}, not
 * here — see that class's docblock). `activate()`/`resume()`/
 * `requestExport()` have no dedicated event class in this item's scope
 * (Files list, P2.12) — they still write a `root_account_events` row.
 *
 * D151 — every mutating method asserts {@see ImpersonationGuard} first
 * (before `activateFromPool`'s pool claim only, per that method's own
 * ordering note).
 */
final class AccountLifecycle
{
    public function __construct(
        private readonly string $centralConnection,
        private readonly PendingPool $pendingPool,
        private readonly AccountLifecycleFsm $fsm,
        private readonly TenantDatabaseLifecycle $tenantDatabaseLifecycle,
        private readonly ImpersonationGuard $impersonationGuard,
    ) {}

    /**
     * Claims a pending-pool row (premortem A6 — atomic via
     * {@see PendingPool::pullPending()}'s own `SELECT … FOR UPDATE SKIP
     * LOCKED`), then races-safely claims it for THIS caller with a
     * `WHERE status='provisioning'`-guarded update, then transitions
     * `provisioning → trial` via {@see startTrial()} (AC-13 platform
     * domain).
     */
    public function activateFromPool(string $slug, string $ownerIdentityId, ?string $vertical): AccountRef
    {
        $claimed = $this->pendingPool->pullPending();

        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $affected = DB::connection($this->centralConnection)->transaction(fn (): int => DB::connection($this->centralConnection)
            ->table('root_accounts')
            ->where('id', $claimed->id)
            ->where('status', 'provisioning')
            ->update([
                'slug' => $slug,
                'owner_identity_id' => $ownerIdentityId,
                'vertical_code' => $vertical,
                'updated_at' => now(),
            ]));

        if ($affected !== 1) {
            throw new RuntimeException("Account [{$claimed->id}] was not in [provisioning] state — concurrent claim?");
        }

        $accountRef = new AccountRef(
            id: $claimed->id,
            slug: $slug,
            status: 'provisioning',
            features: [],
            limits: [],
        );

        $this->startTrial($accountRef);

        return $this->fresh($claimed->id);
    }

    /**
     * `provisioning → trial`; sets `trial_ends_at` and provisions the
     * platform domain (AC-13) — the only transition into `trial`.
     */
    public function startTrial(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $current = $this->currentStatus($account->id);
        $this->fsm->assertTransition($current, 'trial');

        $trialEndsAt = now()->addDays((int) config('tenancy.trial_days', 14));

        DB::connection($this->centralConnection)->transaction(function () use ($account, $trialEndsAt): void {
            DB::connection($this->centralConnection)->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'trial', 'trial_ends_at' => $trialEndsAt, 'updated_at' => now()]);

            $slug = DB::connection($this->centralConnection)->table('root_accounts')->where('id', $account->id)->value('slug');

            DB::connection($this->centralConnection)->table('root_domains')->insert([
                'id' => (string) Str::uuid7(),
                'account_id' => $account->id,
                'host' => $slug.'.'.(string) config('tenancy.platform_domain'),
                'type' => 'platform',
                'is_primary' => true,
                'verified_at' => now(),
                'tls_status' => 'issued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->writeEvent($account->id, 'account.trial_started');
        });

        event(new TrialStarted(account: $account, trialEndsAt: $trialEndsAt->toDateTimeImmutable()));
    }

    /** `trial|suspended → active`. */
    public function activate(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $current = $this->currentStatus($account->id);
        $this->fsm->assertTransition($current, 'active');

        DB::connection($this->centralConnection)->transaction(function () use ($account): void {
            DB::connection($this->centralConnection)->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'active', 'suspended_at' => null, 'updated_at' => now()]);

            $this->writeEvent($account->id, 'account.activated');
        });
    }

    /** `active → suspended`. */
    public function suspend(AccountRef $account, string $reason): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $current = $this->currentStatus($account->id);
        $this->fsm->assertTransition($current, 'suspended');

        DB::connection($this->centralConnection)->transaction(function () use ($account, $reason): void {
            DB::connection($this->centralConnection)->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'suspended', 'suspended_at' => now(), 'updated_at' => now()]);

            $this->writeEvent($account->id, 'account.suspended', ['reason' => $reason]);
        });

        event(new AccountSuspended(account: $account, reason: $reason));
    }

    /** `suspended → active`. */
    public function resume(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $current = $this->currentStatus($account->id);
        $this->fsm->assertTransition($current, 'active');

        DB::connection($this->centralConnection)->transaction(function () use ($account): void {
            DB::connection($this->centralConnection)->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'active', 'suspended_at' => null, 'updated_at' => now()]);

            $this->writeEvent($account->id, 'account.resumed');
        });
    }

    /** `active|suspended|trial → grace`. */
    public function startGrace(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $current = $this->currentStatus($account->id);
        $this->fsm->assertTransition($current, 'grace');

        $graceUntil = now()->addDays((int) config('tenancy.grace_days', 30));
        $purgeAfter = $graceUntil->clone()->addDays(30);

        DB::connection($this->centralConnection)->transaction(function () use ($account, $graceUntil, $purgeAfter): void {
            DB::connection($this->centralConnection)->table('root_accounts')
                ->where('id', $account->id)
                ->update([
                    'status' => 'grace',
                    'grace_until' => $graceUntil,
                    'purge_after' => $purgeAfter,
                    'updated_at' => now(),
                ]);

            $this->writeEvent($account->id, 'account.grace_started');
        });

        event(new GraceStarted(account: $account, graceUntil: $graceUntil->toDateTimeImmutable()));
    }

    /** `grace → exporting`; kicks off {@see TenantDatabaseLifecycle::export()}. */
    public function requestExport(AccountRef $account): string
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $current = $this->currentStatus($account->id);
        $this->fsm->assertTransition($current, 'exporting');

        DB::connection($this->centralConnection)->transaction(function () use ($account): void {
            DB::connection($this->centralConnection)->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'exporting', 'updated_at' => now()]);

            $this->writeEvent($account->id, 'account.export_requested');
        });

        return $this->tenantDatabaseLifecycle->export($account, 'offboarding');
    }

    /**
     * `exporting → deleted`; ONLY when the account's most recent export is
     * `ready` (D13/AC-7). Drops the physical database, then soft-deletes
     * `root_accounts` and hard-deletes its `root_domains` rows.
     */
    public function purge(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $current = $this->currentStatus($account->id);
        $this->fsm->assertTransition($current, 'deleted');

        $latestExportStatus = DB::connection($this->centralConnection)->table('root_account_exports')
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->value('status');

        if ($latestExportStatus !== 'ready') {
            throw ValidationException::withMessages([
                'account' => "Account [{$account->id}] has no ready export on record — cannot purge (export-before-DROP invariant, D13/AC-7).",
            ]);
        }

        DB::connection($this->centralConnection)->transaction(function () use ($account): void {
            DB::connection($this->centralConnection)->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'deleted', 'deleted_at' => now(), 'updated_at' => now()]);

            $this->writeEvent($account->id, 'account.purged');
        });

        // Physical DROP DATABASE cannot run inside a transaction — the
        // FSM/state write above is already committed by this point.
        $this->tenantDatabaseLifecycle->drop($account);

        // Soft-delete (schema convention, root_domains_*_uq partial indexes
        // are WHERE deleted_at IS NULL) — not a hard DELETE, symmetric with
        // root_accounts' own soft-delete above.
        DB::connection($this->centralConnection)->table('root_domains')
            ->where('account_id', $account->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    private function currentStatus(string $accountId): string
    {
        $status = DB::connection($this->centralConnection)->table('root_accounts')->where('id', $accountId)->value('status');

        if ($status === null) {
            throw new RuntimeException("Account [{$accountId}] not found in root_accounts.");
        }

        return (string) $status;
    }

    private function fresh(string $accountId): AccountRef
    {
        $row = DB::connection($this->centralConnection)->table('root_accounts')->where('id', $accountId)->first();

        return new AccountRef(
            id: (string) $row->id,
            slug: (string) $row->slug,
            status: (string) $row->status,
            features: [],
            limits: [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeEvent(string $accountId, string $event, array $payload = []): void
    {
        DB::connection($this->centralConnection)->table('root_account_events')->insert([
            'id' => (string) Str::uuid7(),
            'account_id' => $accountId,
            'event' => $event,
            'actor' => json_encode(['type' => CurrentActor::type()->value, 'id' => CurrentActor::id()], JSON_THROW_ON_ERROR),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
