<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Engine;

use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Contracts\SchemaOperations;
use CoreX\Attributes\Internal\KeyConflict;
use CoreX\Attributes\Internal\ScopeDenied;
use CoreX\Attributes\Support\Schema\DriftReport;
use CoreX\Attributes\Support\Schema\OperationKey;
use CoreX\Attributes\Support\Schema\OperationRef;
use CoreX\Attributes\Support\Schema\SchemaChange;
use CoreX\Attributes\Support\Schema\SchemaReceipt;
use CoreX\Support\Data\DataScope;
use Illuminate\Database\ConnectionInterface;

final readonly class QueuedSchemaOperations implements SchemaOperations
{
    public function __construct(
        private ConnectionInterface $connection,
        private ?FieldCatalogue $catalogue = null,
    ) {}

    public function request(DataScope $scope, SchemaChange $change, OperationKey $key): SchemaReceipt
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);

        if (! ($scope->authorize)($change->entity, '', [], 'schema') || ! ($scope->authorize)($change->entity, '', [$change->approvalDigest], 'schema-approve')) {
            throw new ScopeDenied('The current scope denies this schema operation.');
        }

        if ($this->catalogue !== null && $this->catalogue->snapshot($scope, $change->entity)->schemaHash !== $change->expectedSchemaHash) {
            throw new ScopeDenied('The schema operation was requested against a stale field snapshot.');
        }

        return $this->connection->transaction(function () use ($scope, $change, $key): SchemaReceipt {
            $operations = $this->connection->table('sys_attribute_schema_operations');
            $existing = $operations->where('operation_key', $key->key)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $key->payloadHash) {
                    throw new KeyConflict('A schema operation key is already bound to different data.');
                }

                return new SchemaReceipt(new OperationRef($existing->id), $existing->status, $existing->schema_hash, $existing->error_code);
            }

            $id = hash('sha256', $key->key."\n".$key->payloadHash);
            $operations->insert([
                'id' => $id, 'operation_key' => $key->key, 'payload_hash' => $key->payloadHash,
                'entity' => $change->entity, 'schema_hash' => $change->expectedSchemaHash,
                'approval_digest' => $change->approvalDigest, 'restore_epoch' => $scope->restoreEpoch,
                'definition' => json_encode(['kind' => $change->kind, 'definition' => $change->definition], JSON_THROW_ON_ERROR),
                'status' => 'pending', 'error_code' => null,
            ]);

            return new SchemaReceipt(new OperationRef($id), 'pending', $change->expectedSchemaHash, null);
        });
    }

    public function inspect(DataScope $scope, OperationRef $operation): SchemaReceipt
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);
        $row = $this->connection->table('sys_attribute_schema_operations')->where('id', $operation->id)->first();

        if ($row === null || $row->restore_epoch !== $scope->restoreEpoch || ! ($scope->authorize)($row->entity, '', [], 'schema')) {
            throw new ScopeDenied('The current scope cannot inspect this schema operation.');
        }

        return new SchemaReceipt($operation, $row->status, $row->schema_hash, $row->error_code);
    }

    public function verify(DataScope $scope, string $entity): DriftReport
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);

        if (! ($scope->authorize)($entity, '', [], 'schema')) {
            throw new ScopeDenied('The current scope cannot verify this schema.');
        }

        $rows = $this->connection->table('sys_attribute_schema_operations')->where('entity', $entity)->orderBy('created_at')->get();
        $drift = [];
        $managed = [];
        foreach ($rows as $row) {
            $definition = json_decode($row->definition, true, 16, JSON_THROW_ON_ERROR);
            $field = $definition['definition']['field'] ?? null;

            if (! is_string($field)) {
                $drift[] = ['operation' => $row->id, 'reason' => 'invalid_definition'];

                continue;
            }

            if ($row->status === 'failed') {
                $drift[] = ['operation' => $row->id, 'reason' => 'operation_failed'];

                continue;
            }

            if ($row->status === 'verified' && in_array($definition['kind'] ?? null, ['add', 'change'], strict: true)) {
                $managed[$field] = true;
            }

            if ($row->status === 'verified' && $definition['kind'] === 'drop') {
                $managed[$field] = false;
            }
        }

        foreach ($managed as $field => $expectsIndex) {
            $index = 'sys_attr_doc_'.substr(hash('sha256', $entity."\n".$field), 0, 24);
            $exists = $this->connection->table('pg_indexes')->where('schemaname', 'public')->where('tablename', 'sys_attribute_documents')->where('indexname', $index)->exists();

            if ($exists !== $expectsIndex) {
                $drift[] = ['field' => $field, 'reason' => $expectsIndex ? 'missing_managed_index' : 'unexpected_managed_index'];
            }
        }

        $schemaHash = $this->catalogue?->snapshot($scope, $entity)->schemaHash
            ?? hash('sha256', $entity."\n".json_encode($rows->map(static fn ($row): array => ['id' => $row->id, 'schema_hash' => $row->schema_hash])->all(), JSON_THROW_ON_ERROR));

        return new DriftReport(entity: $entity, schemaHash: $schemaHash, drift: $drift, status: $drift === [] ? 'clean' : 'drifted');
    }
}
