<?php

declare(strict_types=1);

namespace CoreX\Audit\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Data\AuditEntry;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AuditLogger
{
    /**
     * Record an append-only audit entry. The default implementation only
     * enqueues (sync-path budget ≤0.3ms) — the row is written by a queue
     * worker.
     */
    public function record(AuditEntry $entry): void;
}
