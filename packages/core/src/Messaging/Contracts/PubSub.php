<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/**
 * Cross-process pub/sub. The sole channel for cross-process notifications in
 * the kernel — `LISTEN`/`NOTIFY` is forbidden everywhere (incompatible with
 * PgBouncer transaction-mode pooling).
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface PubSub
{
    /** @param array<string, mixed> $payload */
    public function publish(string $channel, array $payload): void;

    /**
     * Long-running processes only (queue worker, Octane tick, a dedicated
     * console listener) — never call this inside an HTTP request cycle.
     *
     * @param  Closure(array<string, mixed>): void  $handler
     */
    public function subscribe(string $channel, Closure $handler): void;
}
