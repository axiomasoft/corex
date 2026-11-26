<?php

declare(strict_types=1);

namespace CoreX\Audit\Internal;

use CoreX\Audit\Data\AuditEntry;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/**
 * Queue worker that writes the sys_audit_log row (the sync path only
 * enqueues this job — see QueuedAuditLogger). occurred_at and workspace_id
 * are the EVENT-time snapshot carried on the AuditEntry: the worker only
 * writes them and NO LONGER resolves tenant here — an async worker runs under a
 * different (or absent) tenant context and would otherwise stamp a foreign
 * workspace and the write moment instead of the event moment.
 *
 * @internal
 */
#[Internal]
final class RecordAuditEntry implements ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly AuditEntry $entry,
        private readonly DataScope $scope,
    ) {}

    public function handle(ClockInterface $clock, CurrentWorkScope $work): void
    {
        $model = new AuditLog;
        $work->assert(scope: $this->scope, connection: $model->getConnection());
        $model->newQuery()->create([
            'occurred_at' => $this->entry->occurredAt ?? $clock->now(),
            'actor_type' => $this->entry->actorType,
            'actor_id' => $this->entry->actorId,
            'workspace_id' => $this->entry->workspaceId,
            'entity_type' => $this->entry->entityType,
            'entity_id' => $this->entry->entityId,
            'action' => $this->entry->action,
            'changes' => JsonDocuments::optionalMap($this->entry->changes),
            'context' => JsonDocuments::map($this->entry->context),
        ]);
    }
}
