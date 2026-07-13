<?php

declare(strict_types=1);

namespace CoreX\PubSub;

use Closure;
use CoreX\Contracts\PubSub;
use Illuminate\Support\Facades\Redis;
use LogicException;
use Override;

/**
 * Cloud-default driver (B-10 §6.1) — `illuminate/redis` PUBLISH/SUBSCRIBE.
 * Never `LISTEN`/`NOTIFY` on the PG connection (R-15).
 */
final class RedisPubSub implements PubSub
{
    public function __construct(
        private readonly ?string $connection = null,
    ) {}

    #[Override]
    public function publish(string $channel, array $payload): void
    {
        Redis::connection($this->connection)->publish($channel, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    #[Override]
    public function subscribe(string $channel, Closure $handler): void
    {
        if (! app()->runningInConsole()) {
            throw new LogicException(
                'RedisPubSub::subscribe() blocks the current process — call it only from a queue worker '.
                'or console command, never inside an HTTP request cycle (B-10 §3.1).',
            );
        }

        Redis::connection($this->connection)->subscribe([$channel], function (string $message) use ($handler): void {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($message, true, flags: JSON_THROW_ON_ERROR);
            $handler($payload);
        });
    }
}
