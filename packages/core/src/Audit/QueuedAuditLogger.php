<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\AuditEntry;
use CoreX\Contracts\AuditLogger;
use CoreX\Tenancy\Contracts\TenantContextResolver;

/**
 * Synchronous enqueue path (B-10 §7.1: the sync-path budget only dispatches;
 * the row is written by RecordAuditEntry on a worker).
 *
 * The event-time snapshot is taken HERE (A17): occurredAt = now() and
 * workspaceId = the CURRENT tenant context — both captured while the request
 * that produced the event is still alive. The worker only writes them; it no
 * longer resolves tenant at write time (an async worker runs under a different
 * or absent tenant context and would stamp a foreign workspace and the write
 * moment). Boxed/CLI installs (SingleAccountResolver) carry workspaceId = null
 * — an honest null, not a foreign tenant.
 *
 * `$connection` is null (the app's default) unless a caller pins an explicit
 * name — forwarded to RecordAuditEntry, same seam as DatabaseSettingsRepository
 * (P1.7), used by the PG-only test lane (D12). `$tenantContext` is optional so
 * a caller can construct the logger directly (test lane); when null it is
 * resolved from the container at record time.
 */
final class QueuedAuditLogger implements AuditLogger
{
    public function __construct(
        private readonly ?string $connection = null,
        private readonly ?TenantContextResolver $tenantContext = null,
    ) {}

    public function record(AuditEntry $entry): void
    {
        $resolver = $this->tenantContext ?? app(TenantContextResolver::class);

        RecordAuditEntry::dispatch(
            $entry->withSnapshot(
                occurredAt: now()->toImmutable(),
                workspaceId: $resolver->current()->workspace?->id,
            ),
            $this->connection,
        );
    }
}
