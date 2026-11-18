<?php

declare(strict_types=1);

namespace CoreX\Audit\Contracts;

use CoreX\Audit\Data\AuditEntry;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

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
