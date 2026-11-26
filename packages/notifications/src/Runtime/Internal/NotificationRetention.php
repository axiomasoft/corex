<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use LogicException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class NotificationRetention
{
    public function __construct(
        private CurrentWorkScope $current,
        private DataScopeFactory $scopes,
        private TenantContextResolver $tenants,
        public int $defaultRetentionDays,
    ) {}

    public function prune(Connection $connection, DateTimeImmutable $now, DateTimeImmutable $cutoff, int $batch): int
    {
        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('Notification retention requires an independent transaction for each batch.');
        }
        $scope = $this->scopes->forCurrentPrincipal($this->tenants->current());
        $total = 0;
        $after = null;
        do {
            [$after, $compacted] = $connection->transaction(function () use ($connection, $scope, $now, $cutoff, $after, $batch): array {
                $this->current->assert(scope: $scope, connection: $connection);
                $query = $connection->table('ntf_notifications')->where(NotificationScope::logical($scope))
                    ->whereNull('pruned_at')->where('read_at', '<', $cutoff);

                if ($after !== null) {
                    $query->whereRowValues(['read_at', 'id'], '>', [$after->read_at, $after->id]);
                }
                // Bound the rows locked and examined, including rows held by unfinished deliveries or digests.
                $rows = $query->oldest('read_at')->orderBy('id')->limit($batch)
                    ->lock($connection->getDriverName() === 'pgsql' ? 'for update skip locked' : true)
                    ->get(['id', 'read_at']);

                if ($rows->isEmpty()) {
                    return [null, 0];
                }
                $compacted = $this->compact(connection: $connection, scope: $scope, ids: NotificationValue::strings($rows->pluck('id')->all()), now: $now, cutoff: $cutoff);

                return [$rows->last(), $compacted];
            }, attempts: 3);
            $total += $compacted;
        } while ($after !== null);

        return $total;
    }

    /** @param list<string> $ids Parent rows are locked in the current transaction. */
    private function compact(Connection $connection, DataScope $scope, array $ids, DateTimeImmutable $now, DateTimeImmutable $cutoff): int
    {
        // Read membership after acquiring the parent locks. A digest builder locks the same parent
        // before freezing an item and excludes tombstones when its lock is released.
        $ids = $connection->table('ntf_notifications')->whereIn('id', $ids)
            ->where(static fn (Builder $query): Builder => $query->whereNull('cancelled_at')->orWhere('cancelled_at', '<', $cutoff))
            ->where(static fn (Builder $query): Builder => $query->whereNull('dismissed_at')->orWhere('dismissed_at', '<', $cutoff))
            ->whereNotExists(static fn (Builder $query): Builder => $query->selectRaw('1')->from('ntf_digest_items')
                ->whereColumn('ntf_digest_items.notification_id', 'ntf_notifications.id'))
            ->whereNotExists(static fn (Builder $query): Builder => $query->selectRaw('1')->from('ntf_deliveries')
                ->whereColumn('ntf_deliveries.notification_id', 'ntf_notifications.id')
                ->where(static fn (Builder $delivery): Builder => $delivery->whereNotIn('state', DeliveryState::finalStates())
                    ->orWhereNot(static fn (Builder $owner): Builder => $owner->where(NotificationScope::logical($scope))->where(NotificationScope::fence($scope)))))
            ->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }
        // The reconciler validates the original message bytes, target and hash of a provider effect.
        // Local receipts only need their immutable identity, hash and outcome.
        $connection->table('ntf_deliveries')->whereIn('notification_id', $ids)
            ->whereNull('provider_namespace')->whereNull('provider_ticket')->update(['message' => null, 'target_reference' => null]);

        return $connection->table('ntf_notifications')->whereIn('id', $ids)
            ->update(['pruned_at' => $now, 'payload' => '{}', 'related' => null]);
    }
}
