<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal\Commands;

use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Runtime\Internal\NotificationValue;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/**
 * Deletes settled notifications past their retention window.
 *
 * A notification is settled once it is read, dismissed or cancelled; unread
 * ones are never pruned. Its deliveries go with it only when every one of
 * them is final, so nothing pending, unknown or quarantined loses its row,
 * and notifications that belong to a digest are kept for the digest's
 * history. Rows are taken in primary-key order in bounded batches, each in
 * its own transaction, so a large backlog never holds one long lock.
 */
#[Internal]
final class PruneNotifications extends Command
{
    /** Delivery states after which no worker or reconciliation touches the row again. */
    private const array FINAL_DELIVERY_STATES = ['accepted', 'completed', 'permanently-failed', 'exhausted', 'skipped', 'cancelled'];

    protected $signature = 'corex:notifications:prune
        {--older-than-days= : Days since a notification was settled (default: corex-notifications.retention.settled_days)}
        {--database= : Connection to prune (default: the app default)}
        {--batch=1000 : Notifications deleted per transaction}';

    protected $description = 'Delete read, dismissed or cancelled notifications older than their retention window.';

    public function handle(ClockInterface $clock, NotificationsOptions $options, ConnectionResolverInterface $connections): int
    {
        $days = $this->positive(name: 'older-than-days', fallback: $options->retentionSettledDays);
        $batch = $this->positive(name: 'batch', fallback: 1000);

        if ($days === null || $batch === null || $batch > 10_000) {
            $this->error('--older-than-days must be a positive integer and --batch between 1 and 10000.');

            return self::INVALID;
        }
        /** @var string|null $name */
        $name = $this->option('database');
        $connection = $connections->connection($name);
        $cutoff = $clock->now()->sub(new DateInterval('P'.$days.'D'));
        $pruned = 0;
        $after = null;

        do {
            [$deleted, $last] = $connection->transaction(fn (): array => $this->pruneBatch(connection: $connection, cutoff: $cutoff, after: $after, batch: $batch));
            $pruned += $deleted;
            $after = $last ?? $after;
        } while ($last !== null);

        $this->info(sprintf('Notification retention: pruned %d settled notification(s) older than %d day(s).', $pruned, $days));

        return self::SUCCESS;
    }

    /** @return array{int, ?string} Deleted count and the last scanned id, null when the scan is done. */
    private function pruneBatch(ConnectionInterface $connection, DateTimeImmutable $cutoff, ?string $after, int $batch): array
    {
        $ids = $this->prunable(connection: $connection, cutoff: $cutoff)
            ->when($after !== null, static fn (Builder $query): Builder => $query->where('n.id', '>', $after))
            ->orderBy('n.id')
            ->limit($batch)
            ->lockForUpdate()
            ->pluck('n.id')
            ->map(NotificationValue::text(...))
            ->all();

        if ($ids === []) {
            return [0, null];
        }
        $connection->table('ntf_deliveries')->whereIn('notification_id', $ids)->delete();
        $deleted = $connection->table('ntf_notifications')->whereIn('id', $ids)->delete();

        return [$deleted, $ids[array_key_last($ids)]];
    }

    private function prunable(ConnectionInterface $connection, DateTimeImmutable $cutoff): Builder
    {
        return $connection->table('ntf_notifications', 'n')
            ->where(static fn (Builder $settled): Builder => $settled
                ->where('n.read_at', '<', $cutoff)
                ->orWhere('n.dismissed_at', '<', $cutoff)
                ->orWhere('n.cancelled_at', '<', $cutoff))
            ->whereNotExists(static fn (Builder $open): Builder => $open->selectRaw('1')->from('ntf_deliveries', 'd')
                ->whereColumn('d.notification_id', 'n.id')
                ->whereNotIn('d.state', self::FINAL_DELIVERY_STATES))
            ->whereNotExists(static fn (Builder $digest): Builder => $digest->selectRaw('1')->from('ntf_digest_items', 'i')
                ->whereColumn('i.notification_id', 'n.id'));
    }

    private function positive(string $name, int $fallback): ?int
    {
        $value = $this->option($name);

        if ($value === null) {
            return $fallback;
        }

        return is_string($value) && preg_match('/^[1-9][0-9]{0,8}$/D', $value) === 1 ? (int) $value : null;
    }
}
