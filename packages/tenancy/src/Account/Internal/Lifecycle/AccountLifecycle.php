<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal\Lifecycle;

use Closure;
use CoreX\Audit\Contracts\CurrentActor;
use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\TenantDatabaseLifecycle;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Enums\ImpersonationRestrictedAction;
use CoreX\Messaging\Events\DomainEvent;
use CoreX\Tenancy\Account\Events\AccountSuspended;
use CoreX\Tenancy\Account\Events\GraceStarted;
use CoreX\Tenancy\Account\Events\TrialStarted;
use CoreX\Tenancy\Account\Internal\DatabaseValue;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy;
use CoreX\Tenancy\Laravel\Support\HostResolverCacheInvalidator;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks as TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * The account FSM (B-11 §5.1/§5.2, P2.12) — `root_accounts.status` MUST be
 * mutated ONLY through this class. Every transition writes one
 * `root_account_events` row ({@see writeEvent()}); the transitions that own
 * a dedicated {@see DomainEvent} subclass also fire it
 * (`startTrial`→TrialStarted, `suspend`→AccountSuspended,
 * `startGrace`→GraceStarted; `AccountPurged`/`ExportReady` are fired by
 * {@see CoreX\Tenancy\Provisioning\Internal\DatabaseTenantLifecycle}, not
 * here — see that class's docblock). `activate()`/`resume()`/
 * `requestExport()` have no dedicated event class in this item's scope
 * (Files list, P2.12) — they still write a `root_account_events` row.
 *
 * D151 — every mutating method asserts {@see ImpersonationGuard} first
 * (before `activateFromPool`'s pool claim only, per that method's own
 * ordering note).
 *
 * @internal
 */
final class AccountLifecycle
{
    public function __construct(
        private readonly string $centralConnection,
        private readonly PendingPool $pendingPool,
        private readonly AccountLifecycleFsm $fsm,
        private readonly TenantDatabaseLifecycle $tenantDatabaseLifecycle,
        private readonly ImpersonationGuard $impersonationGuard,
        private readonly TemplateIntegrityVerifier $templateIntegrityVerifier,
        private readonly AccountLifecyclePolicy $lifecycle,
        private readonly ConnectionResolverInterface $connections,
        private readonly ClockInterface $clock,
        private readonly Dispatcher $events,
        private readonly ConnectionFactory $connectionFactory,
        private readonly HostResolverCacheInvalidator $hostCache,
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
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $claimed = $this->pendingPool->pullPending();

        $affected = $this->db()->transaction(fn (): int => $this->db()
            ->table('root_accounts')
            ->where('id', $claimed->id)
            ->where('status', 'provisioning')
            ->update([
                'slug' => $slug,
                'owner_identity_id' => $ownerIdentityId,
                'vertical_code' => $vertical,
                'updated_at' => $this->clock->now(),
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
        $this->assertAccountIntegrity($account->id);

        $trialEndsAt = $this->clock->now()->modify('+'.$this->lifecycle->trialDays.' days');

        $this->db()->transaction(function () use ($account, $trialEndsAt): void {
            $this->assertLockedTransition($account->id, 'trial');
            $this->db()->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'trial', 'trial_ends_at' => $trialEndsAt, 'updated_at' => $this->clock->now()]);

            $slug = $this->db()->table('root_accounts')->where('id', $account->id)->value('slug');
            $slug = DatabaseValue::text($slug ?? '');

            $this->db()->table('root_domains')->insert([
                'id' => (string) Str::uuid7(),
                'account_id' => $account->id,
                'host' => $slug.'.'.$this->lifecycle->platformDomain,
                'type' => 'platform',
                'is_primary' => true,
                'verified_at' => $this->clock->now(),
                'tls_status' => 'issued',
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);

            $this->writeEvent($account->id, 'account.trial_started');
        });

        $this->events->dispatch(new TrialStarted(account: $account, trialEndsAt: $trialEndsAt));
    }

    /** `trial|suspended → active`. */
    public function activate(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);
        $this->assertAccountIntegrity($account->id);

        $this->db()->transaction(function () use ($account): void {
            $this->assertLockedTransition($account->id, 'active');
            $this->db()->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'active', 'suspended_at' => null, 'updated_at' => $this->clock->now()]);

            $this->writeEvent($account->id, 'account.activated');
        });
    }

    /** `active → suspended`. */
    public function suspend(AccountRef $account, string $reason): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $this->db()->transaction(function () use ($account, $reason): void {
            $this->assertLockedTransition($account->id, 'suspended');
            $this->db()->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'suspended', 'suspended_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);

            $this->writeEvent($account->id, 'account.suspended', ['reason' => $reason]);
        });

        $this->events->dispatch(new AccountSuspended(account: $account, reason: $reason));
    }

    /** `suspended → active`. */
    public function resume(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);
        $this->assertAccountIntegrity($account->id);

        $this->db()->transaction(function () use ($account): void {
            $this->assertLockedTransition($account->id, 'active');
            $this->db()->table('root_accounts')
                ->where('id', $account->id)
                ->update(['status' => 'active', 'suspended_at' => null, 'updated_at' => $this->clock->now()]);

            $this->writeEvent($account->id, 'account.resumed');
        });
    }

    /** `active|suspended|trial → grace`. */
    public function startGrace(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $graceUntil = $this->clock->now()->modify('+'.$this->lifecycle->graceDays.' days');
        $purgeAfter = $graceUntil->modify('+30 days');

        $this->db()->transaction(function () use ($account, $graceUntil, $purgeAfter): void {
            $this->assertLockedTransition($account->id, 'grace');
            $this->db()->table('root_accounts')
                ->where('id', $account->id)
                ->update([
                    'status' => 'grace',
                    'grace_until' => $graceUntil,
                    'purge_after' => $purgeAfter,
                    'updated_at' => $this->clock->now(),
                ]);

            $this->writeEvent($account->id, 'account.grace_started');
        });

        $this->events->dispatch(new GraceStarted(account: $account, graceUntil: $graceUntil));
    }

    /** `grace → exporting`; kicks off {@see TenantDatabaseLifecycle::export()}. */
    public function requestExport(AccountRef $account): string
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        return $this->externalOperation($account->id, function () use ($account): string {
            $this->db()->transaction(function () use ($account): void {
                // An interrupted export can be retried while access remains closed.
                if ($this->currentStatus($account->id) === 'exporting') {
                    return;
                }
                $this->assertLockedTransition($account->id, 'exporting');
                $this->db()->table('root_accounts')
                    ->where('id', $account->id)
                    ->update(['status' => 'exporting', 'updated_at' => $this->clock->now()]);

                $this->writeEvent($account->id, 'account.export_requested');
            });

            return $this->tenantDatabaseLifecycle->export($account, 'offboarding');
        });
    }

    /**
     * `exporting → deleted`; ONLY when the account's most recent export is
     * `ready` (D13/AC-7). Drops the physical database, then soft-deletes
     * `root_accounts` and hard-deletes its `root_domains` rows.
     */
    public function purge(AccountRef $account): void
    {
        $this->impersonationGuard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);

        $this->externalOperation($account->id, function () use ($account): void {
            $current = $this->currentStatus($account->id);

            if ($current === 'deleted') {
                return;
            }
            $this->fsm->assertTransition($current, 'deleted');

            $latestExportStatus = $this->db()->table('root_account_exports')
                ->where('account_id', $account->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('status');

            if ($latestExportStatus !== 'ready') {
                throw ValidationException::withMessages([
                    'account' => "Account [{$account->id}] has no ready export on record — cannot purge (export-before-DROP invariant, D13/AC-7).",
                ]);
            }

            // Keep exporting until DROP succeeds. IF EXISTS makes retry after a crash safe.
            $this->tenantDatabaseLifecycle->drop($account);

            $this->db()->transaction(function () use ($account): void {
                $this->assertLockedTransition($account->id, 'deleted');
                $this->db()->table('root_accounts')
                    ->where('id', $account->id)
                    ->update(['status' => 'deleted', 'deleted_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);

                // Soft-delete (schema convention, root_domains_*_uq partial indexes
                // are WHERE deleted_at IS NULL) — not a hard DELETE, symmetric with
                // root_accounts' own soft-delete above.
                $this->db()->table('root_domains')
                    ->where('account_id', $account->id)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);

                $this->writeEvent($account->id, 'account.purged');
            });
        });
    }

    private function assertLockedTransition(string $accountId, string $to): void
    {
        $status = $this->db()->table('root_accounts')
            ->where('id', $accountId)->lockForUpdate()->value('status');

        if ($status === null) {
            throw new RuntimeException("Account [{$accountId}] not found in root_accounts.");
        }
        $this->fsm->assertTransition(DatabaseValue::text($status), $to);
    }

    private function assertAccountIntegrity(string $accountId): void
    {
        $account = Account::on($this->centralConnection)->findOrFail($accountId);

        $this->templateIntegrityVerifier->verifyAccount(new TemplateAccountView($account->template_version, $account->cluster_id, $account->db_name));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    private function externalOperation(string $accountId, Closure $operation): mixed
    {
        $connection = $this->db();

        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException('Export and purge must run outside a transaction.');
        }
        $key = 'corex-account-external:'.$accountId;
        // A separate transaction holds the lock while DROP DATABASE executes
        // outside a transaction on the central connection (PgBouncer-safe).
        $configuration = $connection->getConfig();

        if (! is_array($configuration)) {
            throw new RuntimeException('Account operation connection configuration is unavailable.');
        }
        $lock = $this->connectionFactory
            ->make($configuration, 'corex-account-operation-lock');

        try {
            return $lock->transaction(static function () use ($lock, $key, $operation): mixed {
                $lock->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);

                return $operation();
            });
        } finally {
            $lock->disconnect();
        }
    }

    private function currentStatus(string $accountId): string
    {
        $status = $this->db()->table('root_accounts')->where('id', $accountId)->value('status');

        if ($status === null) {
            throw new RuntimeException("Account [{$accountId}] not found in root_accounts.");
        }

        return DatabaseValue::text($status);
    }

    private function fresh(string $accountId): AccountRef
    {
        $row = $this->db()->table('root_accounts')->where('id', $accountId)->first();

        if ($row === null) {
            throw new RuntimeException("Account [{$accountId}] not found in root_accounts.");
        }

        return new AccountRef(
            id: DatabaseValue::text($row->id),
            slug: DatabaseValue::text($row->slug ?? ''),
            status: DatabaseValue::text($row->status),
            features: [],
            limits: [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeEvent(string $accountId, string $event, array $payload = []): void
    {
        $this->db()->afterCommit(function () use ($accountId): void {
            $this->hostCache->forgetAccount($accountId);
        });

        $this->db()->table('root_account_events')->insert([
            'id' => (string) Str::uuid7(),
            'account_id' => $accountId,
            'event' => $event,
            'actor' => json_encode(['type' => CurrentActor::type()->value, 'id' => CurrentActor::id()], JSON_THROW_ON_ERROR),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
    }

    private function db(): Connection
    {
        /** @var Connection */
        $connection = $this->connections->connection($this->centralConnection);

        return $connection;
    }
}
