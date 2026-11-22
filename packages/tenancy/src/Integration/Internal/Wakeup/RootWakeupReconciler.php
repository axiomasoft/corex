<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Integration\Internal\Wakeup;

use Closure;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;
use CoreX\Tenancy\Integration\Internal\DatabaseValue;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Throwable;

/**
 * @internal Host-first wakeup adapter. Not package public API.
 *
 * Claims one root hint at a time and invokes the tenant-local sweeper
 * under the host's named account service grant.
 */
#[Internal]
final readonly class RootWakeupReconciler
{
    private const string SERVICE = 'corex.scheduler-sweep';

    public function __construct(
        private ConnectionResolverInterface $connections,
        private string $rootConnection,
        private ClockInterface $clock,
        private RootRegistryWakeupHint $hint,
        private TenancyManager $tenancy,
        private NamedWorkPrincipal $namedPrincipals,
        private DataScopeFactory $scopes,
        private DueWakeupSweeper $sweeper,
        private int $leaseSeconds,
    ) {}

    public function sweep(int $limit): int
    {
        if ($limit < 1 || $limit > 1_000) {
            throw new RuntimeException('Root wakeup sweep limit must be between 1 and 1000.');
        }

        $swept = 0;

        while ($swept < $limit) {
            $claim = $this->claimNext();

            if ($claim === null) {
                return $swept;
            }

            $this->runClaim($claim);
            $swept++;
        }

        return $swept;
    }

    /**
     * Rebuilds advisory root hints from every eligible account in one bounded
     * page. Its root-local cursor and bounded retry state deliberately have no
     * Redis or root-hint-table dependency.
     *
     * @return array{processed: int, next_cursor: string|null}
     */
    public function reconcile(int $limit, ?string $afterAccountId = null): array
    {
        if ($limit < 1 || $limit > 1_000) {
            throw new RuntimeException('Root wakeup reconciliation limit must be between 1 and 1000.');
        }

        $state = $this->root()->transaction(function (): object {
            $state = $this->root()->table('root_wakeup_reconciliation_state')
                ->where('name', 'default')
                ->lockForUpdate()
                ->first();

            if ($state !== null) {
                return $state;
            }

            $now = $this->clock->now();
            $this->root()->table('root_wakeup_reconciliation_state')->insert([
                'name' => 'default',
                'cursor_account_id' => null,
                'attempts' => 0,
                'retry_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->root()->table('root_wakeup_reconciliation_state')->where('name', 'default')->firstOrFail();
        });

        if ($state->retry_at !== null && new DateTimeImmutable(DatabaseValue::text($state->retry_at)) > $this->clock->now()) {
            return ['processed' => 0, 'next_cursor' => $state->cursor_account_id === null ? null : DatabaseValue::text($state->cursor_account_id)];
        }

        $cursor = $afterAccountId ?? ($state->cursor_account_id === null ? null : DatabaseValue::text($state->cursor_account_id));
        $accounts = $this->root()->table('root_accounts')
            ->where('status', 'active')
            ->when($cursor !== null, static fn (Builder $query) => $query->where('id', '>', $cursor))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'slug', 'status']);
        $cursor = null;

        foreach ($accounts as $account) {
            $accountRef = new AccountRef(
                id: DatabaseValue::text($account->id),
                slug: DatabaseValue::text($account->slug ?? ''),
                status: DatabaseValue::text($account->status),
                features: [],
                limits: [],
            );
            $context = new TenantContext(account: $accountRef);

            try {
                $nextFireAt = $this->inAccount(
                    context: $context,
                    work: fn (): ?DateTimeImmutable => $this->sweeper->nextDue(context: $context),
                );
            } catch (Throwable) {
                $attempts = DatabaseValue::integer($state->attempts) + 1;
                $this->root()->table('root_wakeup_reconciliation_state')->where('name', 'default')->update([
                    'attempts' => $attempts,
                    'retry_at' => $this->clock->now()->add(new DateInterval('PT'.(min(300, 2 ** min($attempts, 8))).'S')),
                    'updated_at' => $this->clock->now(),
                ]);

                return ['processed' => 0, 'next_cursor' => $cursor];
            }

            if ($nextFireAt !== null) {
                $this->hint->scheduled(context: $context, fireAt: $nextFireAt);
            }

            $cursor = $accountRef->id;
        }

        $nextCursor = $accounts->count() === $limit ? $cursor : null;
        $this->root()->table('root_wakeup_reconciliation_state')->where('name', 'default')->update([
            'cursor_account_id' => $nextCursor,
            'attempts' => 0,
            'retry_at' => null,
            'updated_at' => $this->clock->now(),
        ]);

        return ['processed' => $accounts->count(), 'next_cursor' => $nextCursor];
    }

    /** @return array{account: AccountRef, revision: int, epoch: int}|null */
    private function claimNext(): ?array
    {
        return $this->root()->transaction(function (): ?array {
            while (true) {
                $now = $this->clock->now();
                $hint = $this->root()->table('root_wakeup_hints')
                    ->where('next_fire_at', '<=', $now)
                    ->where(static fn (Builder $query) => $query->whereNull('leased_until')->orWhere('leased_until', '<=', $now))
                    ->oldest('next_fire_at')
                    ->lockForUpdate()
                    ->first();

                if ($hint === null) {
                    return null;
                }

                $account = $this->root()->table('root_accounts')->where('id', $hint->account_id)->first();

                if ($account === null) {
                    $this->root()->table('root_wakeup_hints')->where('account_id', $hint->account_id)->delete();

                    continue;
                }

                $epoch = DatabaseValue::integer($hint->claim_epoch) + 1;
                $this->root()->table('root_wakeup_hints')
                    ->where('account_id', $hint->account_id)
                    ->where('hint_revision', $hint->hint_revision)
                    ->where('claim_epoch', $hint->claim_epoch)
                    ->update([
                        'claim_epoch' => $epoch,
                        'leased_until' => $now->add(new DateInterval('PT'.$this->leaseSeconds.'S')),
                        'updated_at' => $now,
                    ]);

                return [
                    'account' => new AccountRef(
                        id: DatabaseValue::text($account->id),
                        slug: DatabaseValue::text($account->slug ?? ''),
                        status: DatabaseValue::text($account->status),
                        features: [],
                        limits: [],
                    ),
                    'revision' => DatabaseValue::integer($hint->hint_revision),
                    'epoch' => $epoch,
                ];
            }
        });
    }

    /** @param array{account: AccountRef, revision: int, epoch: int} $claim */
    private function runClaim(array $claim): void
    {
        $context = new TenantContext(account: $claim['account']);
        $nextFireAt = $this->inAccount(
            context: $context,
            work: function () use ($context): ?DateTimeImmutable {
                $this->sweeper->sweep(context: $context, limit: 100);

                return $this->sweeper->nextDue(context: $context);
            },
        );

        $this->root()->transaction(function () use ($claim, $nextFireAt): void {
            $query = $this->root()->table('root_wakeup_hints')
                ->where('account_id', $claim['account']->id)
                ->where('hint_revision', $claim['revision'])
                ->where('claim_epoch', $claim['epoch']);

            if ($nextFireAt === null) {
                $query->delete();

                return;
            }

            $query->update([
                'next_fire_at' => $nextFireAt,
                'leased_until' => null,
                'updated_at' => $this->clock->now(),
            ]);
        });
    }

    /** @param  Closure():?DateTimeImmutable  $work */
    private function inAccount(TenantContext $context, Closure $work): ?DateTimeImmutable
    {
        return $this->tenancy->runFor(account: $context->account, workspace: null, callback: fn (): ?DateTimeImmutable => $this->namedPrincipals->run(
            context: $context,
            serviceId: self::SERVICE,
            work: function () use ($context, $work): ?DateTimeImmutable {
                $scope = $this->scopes->forCurrentPrincipal($context);

                if ($scope->principalKind !== 'service' || $scope->principalId !== self::SERVICE) {
                    throw new ScopeContextMismatch('Root wakeup requires the scoped Scheduler service grant.');
                }

                return $work();
            },
        ));
    }

    private function root(): ConnectionInterface
    {
        return $this->connections->connection($this->rootConnection);
    }
}
