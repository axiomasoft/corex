<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\AuditEntry;
use CoreX\Contracts\AuditLogger;

/**
 * `$connection` is null (the app's default) unless a caller pins an explicit
 * name — forwarded to RecordAuditEntry, same seam as DatabaseSettingsRepository
 * (P1.7), used by the PG-only test lane (D12).
 */
final class QueuedAuditLogger implements AuditLogger
{
    public function __construct(private readonly ?string $connection = null) {}

    public function record(AuditEntry $entry): void
    {
        RecordAuditEntry::dispatch($entry, $this->connection);
    }
}
