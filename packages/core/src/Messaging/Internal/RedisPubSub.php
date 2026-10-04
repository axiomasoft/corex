<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use Closure;
use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Contracts\PubSub;
use CoreX\Messaging\Contracts\WorkerRuntime;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Override;

/**
 * Cloud-default driver — `illuminate/redis` PUBLISH/SUBSCRIBE. Never
 * `LISTEN`/`NOTIFY` on the PG connection (R-15). Redis PUBLISH/SUBSCRIBE
 * has no message history at all (unlike the database-poll driver's table) —
 * a subscriber that was down misses everything published during the outage,
 * by design of the transport. No durable cursor is possible here — that is
 * out of scope for this driver, the database driver provides it instead.
 *
 * @internal spec: B-10 §6.1, D44
 */
final class RedisPubSub implements PubSub
{
    public function __construct(
        private readonly RedisFactory $redis,
        private readonly CoreOptions $options,
        private readonly WorkerRuntime $runtime,
        private readonly ?string $connection = null,
    ) {}

    #[Override]
    public function publish(string $channel, array $payload): void
    {
        $this->redis->connection($this->connection)->publish($channel, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    #[Override]
    public function subscribe(string $channel, Closure $handler): void
    {
        SubscribeGuard::assert($this->runtime, $this->options->pubsubOctaneWorkerOptIn, self::class);

        $this->redis->connection($this->connection)->subscribe([$channel], function (string $message) use ($handler): void {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($message, true, flags: JSON_THROW_ON_ERROR);
            $handler($payload);
        });
    }
}
