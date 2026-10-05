<?php

declare(strict_types=1);

namespace CoreX\Audit\Internal;

use CoreX\Audit\Contracts\SynchronousAuditWriter;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Data\Laravel\JsonDocuments;
use Illuminate\Database\ConnectionResolverInterface;
use RuntimeException;

/** @internal */
final readonly class DatabaseSynchronousAuditWriter implements SynchronousAuditWriter
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    public function record(AuditEntry $entry, string $connectionName): void
    {
        $connection = $this->connections->connection($connectionName);

        if ($connection->transactionLevel() !== 0 || $entry->occurredAt === null) {
            throw new RuntimeException('Synchronous audit requires a snapshot and no ambient transaction');
        }

        $connection->transaction(static function () use ($entry, $connectionName): void {
            $row = new AuditLog([
                'occurred_at' => $entry->occurredAt,
                'actor_type' => $entry->actorType,
                'actor_id' => $entry->actorId,
                'workspace_id' => $entry->workspaceId,
                'entity_type' => $entry->entityType,
                'entity_id' => $entry->entityId,
                'action' => $entry->action,
                'changes' => JsonDocuments::optionalMap($entry->changes),
                'context' => JsonDocuments::map($entry->context),
            ]);
            $row->setConnection($connectionName);

            foreach ($row->uniqueIds() as $column) {
                $row->setAttribute($column, $row->newUniqueId());
            }

            if (! $row->saveQuietly()) {
                throw new RuntimeException('Synchronous audit insert failed');
            }
        });
    }
}
