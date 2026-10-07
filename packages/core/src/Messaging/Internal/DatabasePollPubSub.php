<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use Closure;
use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Contracts\PubSub;
use CoreX\Messaging\Contracts\WorkerRuntime;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Sleep;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Boxed fallback driver for installs without Redis — polls
 * `sys_pubsub_messages` by an auto-increment cursor instead of `LISTEN`/
 * `NOTIFY` (forbidden under PgBouncer transaction pooling, R-15). `id` is a
 * plain bigint (not UUIDv7/ADR-003) — this table is an internal delivery
 * cursor, not a domain entity.
 *
 * DURABLE cursor: the poll position for a `($channel,
 * $subscriber)` pair is persisted in `sys_pubsub_cursors`, not restarted at 0
 * or at `max(id)` on every `subscribe()` call. A rolling-restart of the
 * subscriber process resumes exactly where the previous process left off —
 * this driver backs the RBAC/module-activity invalidation channel, and a
 * "start from max(id) on every subscribe()" design would silently DROP
 * every message published during the restart window (stale cache, not a
 * storm). The corresponding row in `sys_pubsub_cursors` is created only the
 * FIRST time a given `$subscriber` name is seen for a channel: at that point
 * there is no prior position to resume from, so it starts at the channel's
 * current `max(id)` (skips pre-existing backlog) unless `$replayBacklog` is
 * passed explicitly — never a default.
 *
 * `$connection` mirrors the other PG-only drivers' seam (null = app
 * default). `$maxPolls` is test-only: `null` polls forever (real usage), a
 * finite count lets tests observe delivery without blocking — it does NOT
 * bypass the worker-context guard (SubscribeGuard runs regardless).
 *
 * @internal spec: B-10 §6.1, D44, P1.24, Implementation Rules, pre-mortem C7
 */
final readonly class DatabasePollPubSub implements PubSub
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private CoreOptions $options,
        private ClockInterface $clock,
        private WorkerRuntime $runtime,
        private ?string $connection = null,
        private ?int $pollIntervalMicroseconds = null,
    ) {}

    #[Override]
    public function publish(string $channel, array $payload): void
    {
        $connection = $this->connections->connection($this->connection);

        $connection->transaction(function () use ($channel, $payload, $connection): void {
            $connection->select(
                query: 'SELECT pg_advisory_xact_lock(hashtext(?))',
                bindings: ['corex-pubsub:'.$channel],
            );

            $connection->table('sys_pubsub_messages')->insert([
                'channel' => $channel,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => $this->clock->now(),
            ]);
        });
    }

    /**
     * @param  Closure(array<string, mixed>): void  $handler
     * @param  int|null  $maxPolls  test-only: stop after N poll iterations instead of polling forever.
     * @param  string  $subscriber  durable-cursor identity — a distinct name per logical
     *                              subscriber lets several independent consumers each track their own
     *                              position on the same channel.
     * @param  bool  $replayBacklog  only meaningful the first time this `$subscriber` subscribes to
     *                               `$channel` (no persisted cursor yet): `true` starts at the beginning
     *                               of the channel's history instead of the current `max(id)`. Never
     *                               defaults to `true`.
     *
     * @internal spec: D44, Implementation Rules, P1.24
     */
    public function subscribe(
        string $channel,
        Closure $handler,
        ?int $maxPolls = null,
        string $subscriber = 'default',
        bool $replayBacklog = false,
    ): void {
        SubscribeGuard::assert(runtime: $this->runtime, octaneWorkerOptIn: $this->options->pubsubOctaneWorkerOptIn, driverClass: self::class);

        $connection = $this->connections->connection($this->connection);
        $messagesTable = 'sys_pubsub_messages';
        $cursorsTable = 'sys_pubsub_cursors';

        $cursor = $this->loadOrBootstrapCursor(connection: $connection, cursorsTable: $cursorsTable, messagesTable: $messagesTable, channel: $channel, subscriber: $subscriber, replayBacklog: $replayBacklog);
        $polls = 0;

        while ($maxPolls === null || $polls < $maxPolls) {
            $rows = $connection->table($messagesTable)
                ->where('channel', $channel)
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                assert((is_int($row->id) || is_numeric($row->id)) && is_string($row->payload));
                $cursor = (int) $row->id;
                /** @var array<string, mixed> $payload */
                $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
                $handler($payload);

                $this->persistCursor(connection: $connection, cursorsTable: $cursorsTable, channel: $channel, subscriber: $subscriber, position: $cursor);
            }

            $polls++;

            if ($maxPolls === null || $polls < $maxPolls) {
                Sleep::usleep($this->pollIntervalMicroseconds ?? $this->options->pubsubPollIntervalMicroseconds);
            }
        }
    }

    private function loadOrBootstrapCursor(
        ConnectionInterface $connection,
        string $cursorsTable,
        string $messagesTable,
        string $channel,
        string $subscriber,
        bool $replayBacklog,
    ): int {
        $existing = $connection->table($cursorsTable)
            ->where('channel', $channel)
            ->where('subscriber', $subscriber)
            ->first();

        if ($existing !== null) {
            assert(is_int($existing->position) || is_numeric($existing->position));

            return (int) $existing->position;
        }

        $maximum = $replayBacklog ? 0 : ($connection->table($messagesTable)->where('channel', $channel)->max('id') ?? 0);
        assert(is_int($maximum) || is_numeric($maximum));
        $start = (int) $maximum;

        $this->persistCursor(connection: $connection, cursorsTable: $cursorsTable, channel: $channel, subscriber: $subscriber, position: $start);

        return $start;
    }

    private function persistCursor(
        ConnectionInterface $connection,
        string $cursorsTable,
        string $channel,
        string $subscriber,
        int $position,
    ): void {
        $connection->table($cursorsTable)->updateOrInsert(
            ['channel' => $channel, 'subscriber' => $subscriber],
            ['position' => $position, 'updated_at' => $this->clock->now()],
        );
    }
}
