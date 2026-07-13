<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\AuditEntry;
use CoreX\Models\AuditLog;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue worker that writes the sys_audit_log row (B-10 §7.1: the sync path
 * only enqueues this job — see QueuedAuditLogger). workspace_id is resolved
 * here, at write time, from the current TenantContext — AuditEntry itself
 * carries no workspace (B-10 §3.1 constructor).
 *
 * `$writeConnection` is null (the app's default) unless a caller pins an
 * explicit name — same seam as DatabaseRecordsRegistrar (P1.6)/
 * DatabaseSettingsRepository (P1.7), used by the PG-only test lane (D12)
 * since sys_audit_log has no sqlite equivalent. Named apart from
 * `$connection` — Illuminate\Bus\Queueable already declares that property
 * for the job's own queue connection.
 */
final class RecordAuditEntry implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly AuditEntry $entry,
        private readonly ?string $writeConnection = null,
    ) {}

    public function handle(TenantContextResolver $tenantContext): void
    {
        AuditLog::on($this->writeConnection)->create([
            'occurred_at' => now(),
            'actor_type' => $this->entry->actorType,
            'actor_id' => $this->entry->actorId,
            'workspace_id' => $tenantContext->current()->workspace?->id,
            'entity_type' => $this->entry->entityType,
            'entity_id' => $this->entry->entityId,
            'action' => $this->entry->action,
            'changes' => $this->entry->changes,
            'context' => $this->entry->context,
        ]);
    }
}
