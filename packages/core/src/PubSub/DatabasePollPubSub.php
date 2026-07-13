<?php

declare(strict_types=1);

namespace CoreX\PubSub;

use Closure;
use CoreX\Contracts\PubSub;
use CoreX\Support\Config;
use Illuminate\Support\Facades\DB;
use LogicException;
use Override;

/**
 * Boxed fallback driver for installs without Redis (B-10 §6.1) — polls
 * `sys_pubsub_messages` by an auto-increment cursor instead of `LISTEN`/
 * `NOTIFY` (forbidden under PgBouncer transaction pooling, R-15). `id` is a
 * plain bigint (not UUIDv7/ADR-003) — this table is an internal delivery
 * cursor, not a domain entity.
 *
 * `$connection` mirrors the other PG-only drivers' seam (null = app
 * default). `$maxPolls` is test-only: `null` polls forever (real usage),
 * a finite count lets tests observe delivery without blocking.
 */
final class DatabasePollPubSub implements PubSub
{
    public function __construct(
        private readonly ?string $connection = null,
        private readonly ?int $pollIntervalMicroseconds = null,
    ) {}

    #[Override]
    public function publish(string $channel, array $payload): void
    {
        DB::connection($this->connection)->table(Config::pubsubMessagesTable())->insert([
            'channel' => $channel,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    #[Override]
    public function subscribe(string $channel, Closure $handler, ?int $maxPolls = null): void
    {
        if ($maxPolls === null && ! app()->runningInConsole()) {
            throw new LogicException(
                'DatabasePollPubSub::subscribe() polls forever — call it only from a queue worker or '.
                'console command, never inside an HTTP request cycle (B-10 §3.1).',
            );
        }

        // Cursor starts at 0, not the current max id: a single subscribe()
        // call is meant to run once, forever (a queue worker started at
        // deploy) — it drains any backlog already on the channel before
        // moving on to newly published messages. No cross-call cursor
        // persistence (out of contract scope, Scope Excluded).
        $table = Config::pubsubMessagesTable();
        $cursor = 0;
        $polls = 0;

        while ($maxPolls === null || $polls < $maxPolls) {
            $rows = DB::connection($this->connection)->table($table)
                ->where('channel', $channel)
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $cursor = (int) $row->id;
                /** @var array<string, mixed> $payload */
                $payload = json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);
                $handler($payload);
            }

            $polls++;

            if ($maxPolls === null || $polls < $maxPolls) {
                usleep($this->pollIntervalMicroseconds ?? Config::pubsubPollIntervalMicroseconds());
            }
        }
    }
}
