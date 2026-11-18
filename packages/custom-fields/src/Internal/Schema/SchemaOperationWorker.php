<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Data\OperationRef;
use CoreX\CustomFields\Internal\SchemaOperationConflict;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Storage\ScopeConnectionGuard;
use CoreX\CustomFields\Internal\Value;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use DateInterval;
use DateTimeImmutable;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Throwable;

#[Internal]
final readonly class SchemaOperationWorker
{
    public function __construct(
        private ConnectionInterface $connection,
        private AttributeTables $tables,
        private DataScopeAuthorizer $authorizer,
        private readonly ClockInterface $clock,
    ) {}

    public function run(DataScope $scope, OperationRef $operation): void
    {
        new ScopeConnectionGuard($this->connection)->assertMatches($scope);
        $row = $this->claim(scope: $scope, operation: $operation);

        try {
            $definition = json_decode(Value::text(Value::field(row: $row, name: 'definition')), true, 16, JSON_THROW_ON_ERROR);
            $this->apply(entity: Value::text(Value::field(row: $row, name: 'entity')), definition: Value::map($definition));

            $this->connection->table($this->tables->schemaOperations)->where('id', $operation->id)->update([
                'status' => 'verified',
                'error_code' => null,
                'finished_at' => $this->clock->now(),
            ]);
        } catch (Throwable $exception) {
            $this->connection->table($this->tables->schemaOperations)->where('id', $operation->id)->update([
                'status' => 'failed',
                'error_code' => 'ddl_execution_failed',
                'finished_at' => $this->clock->now(),
            ]);

            throw $exception;
        }
    }

    private function claim(DataScope $scope, OperationRef $operation): object
    {
        return $this->connection->transaction(function () use ($scope, $operation): object {
            $operations = $this->connection->table($this->tables->schemaOperations);
            $row = $operations->where('id', $operation->id)->lockForUpdate()->first();

            if ($row === null || $row->restore_epoch !== $scope->restoreEpoch
                || ! $this->authorizer->authorize(scope: $scope, entity: Value::text($row->entity), subjectId: '', fields: [], action: 'schema')->allowed
                || ! $this->authorizer->authorize(scope: $scope, entity: Value::text($row->entity), subjectId: '', fields: Value::strings([$row->approval_digest]), action: 'schema-approve')->allowed) {
                throw new ScopeDenied('The schema operation cannot run in the current scope.');
            }

            if ($row->status === 'running' && $row->started_at !== null && new DateTimeImmutable(Value::text($row->started_at)) <= new DateTimeImmutable(Value::text('-10 minutes'))) {
                $operations->where('id', $operation->id)->update(['status' => 'pending', 'error_code' => 'stale_worker_reclaimed']);
                $row->status = 'pending';
            }

            if ($row->status !== 'pending') {
                throw SchemaOperationConflict::notReady();
            }

            if ($row->attempts >= 5) {
                throw SchemaOperationConflict::retryExhausted();
            }

            $operations->where('id', $operation->id)->update([
                'status' => 'running',
                'attempts' => Value::integer($row->attempts) + 1,
                'started_at' => $this->clock->now(),
                'error_code' => null,
            ]);

            return $row;
        });
    }

    /** @param array<string, mixed> $definition */
    private function apply(string $entity, array $definition): void
    {
        $kind = $definition['kind'] ?? null;
        $payload = $definition['definition'] ?? null;
        $field = is_array($payload) ? $payload['field'] ?? null : null;

        if (! is_string($kind) || ! is_array($payload) || ! is_string($field) || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $field) !== 1) {
            throw new RuntimeException('Schema operation definition is invalid.');
        }

        $index = $this->indexName(entity: $entity, field: $field);

        if (in_array($kind, ['add', 'change'], strict: true)) {
            $this->ensureIndex(entity: $entity, field: $field, index: $index);

            return;
        }

        if ($kind === 'retire') {
            return;
        }

        if ($kind === 'drop') {
            $this->dropRetiredIndex(index: $index, definition: Value::map($payload));

            return;
        }

        throw new RuntimeException('Schema operation kind is invalid.');
    }

    private function ensureIndex(string $entity, string $field, string $index): void
    {
        if (! $this->indexExists($index)) {
            $fieldLiteral = $this->literal($field);
            $entityLiteral = $this->literal($entity);
            // PostgreSQL forbids CREATE INDEX CONCURRENTLY in a transaction. Claiming is complete above.
            $this->connection->statement("CREATE INDEX CONCURRENTLY {$index} ON {$this->tables->documents} ((values->>{$fieldLiteral})) WHERE entity = {$entityLiteral}");
        }

        if (! $this->indexExists($index)) {
            throw new RuntimeException('The managed attribute index was not created.');
        }
    }

    /** @param array<string, mixed> $definition */
    private function dropRetiredIndex(string $index, array $definition): void
    {
        $retiredAt = $definition['retired_at'] ?? null;
        $exportHash = $definition['export_hash'] ?? null;

        if (! is_string($retiredAt) || ! is_string($exportHash) || preg_match('/\A[a-f0-9]{64}\z/D', $exportHash) !== 1) {
            throw new RuntimeException('Dropping an index requires a dated retirement and export digest.');
        }

        try {
            $retired = new DateTimeImmutable($retiredAt);
        } catch (Exception $exception) {
            throw new RuntimeException('Schema retirement date is invalid.', previous: $exception);
        }

        if ($retired->add(new DateInterval('P30D')) > $this->clock->now()) {
            throw new RuntimeException('Managed index retirement grace period has not elapsed.');
        }

        if ($this->indexExists($index)) {
            $this->connection->statement("DROP INDEX CONCURRENTLY {$index}");
        }
    }

    private function indexExists(string $index): bool
    {
        return $this->connection->table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', $this->tables->documents)
            ->where('indexname', $index)
            ->exists();
    }

    private function indexName(string $entity, string $field): string
    {
        return 'sys_attr_doc_'.substr(hash('sha256', $entity."\n".$field), 0, 24);
    }

    private function literal(string $value): string
    {
        // Both callers validate opaque identifiers before this point; neither grammar admits a quote.
        return "'{$value}'";
    }
}
