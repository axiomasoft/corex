<?php

declare(strict_types=1);

namespace CoreX\Audit\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Data\AuditEntry;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SynchronousAuditWriter
{
    /** Persist and commit before returning; reject an ambient transaction. */
    public function record(AuditEntry $entry, string $connectionName): void;
}
