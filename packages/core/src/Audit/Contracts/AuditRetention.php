<?php

declare(strict_types=1);

namespace CoreX\Audit\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use DateTimeImmutable;

/**
 * Retention policy for the append-only audit journal. `olderThan()` returns
 * the cutoff: rows with occurred_at STRICTLY BEFORE it may be pruned by
 * `corex:audit:prune`. `null` means "no boundary" — the boxed-safe default
 * (NullAuditRetention) keeps everything forever.
 *
 * Per-tariff retention numbers (billing) are out of scope here — a host
 * binds its own implementation.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AuditRetention
{
    public function olderThan(): ?DateTimeImmutable;
}
