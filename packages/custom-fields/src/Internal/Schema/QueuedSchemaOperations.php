<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Contracts\SchemaOperations;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Data\DriftReport;
use CoreX\CustomFields\Data\OperationRef;
use CoreX\CustomFields\Data\SchemaChange;
use CoreX\CustomFields\Data\SchemaOperationId;
use CoreX\CustomFields\Data\SchemaReceipt;
use CoreX\CustomFields\Internal\KeyConflict;
use CoreX\CustomFields\Internal\SchemaOperationConflict;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Support\Validation;
use CoreX\CustomFields\Internal\Value;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

#[Internal]
final readonly class QueuedSchemaOperations implements SchemaOperations
{
    private SchemaOperationAdmission $admission;

    public function __construct(
        private ConnectionInterface $connection,
        private CurrentWorkScope $current,
        private AttributeTables $tables,
        private FieldCatalogue $catalogue,
        DataScopeAuthorizer $authorizer,
    ) {
        $this->admission = new SchemaOperationAdmission($authorizer);
    }

    public function request(DataScope $scope, SchemaChange $change, SchemaOperationId $key): SchemaReceipt
    {
        $this->current->assert($scope, $this->connection);

        if (! $this->admission->allows($scope, $change->entity, FieldUse::Write)
            || ! $this->admission->approves($scope, $change->approvalDigest)) {
            throw new ScopeDenied('The current scope denies this schema operation.');
        }

        if ($this->catalogue->snapshot(scope: $scope, entity: $change->entity)->schemaHash !== $change->expectedSchemaHash) {
            throw SchemaOperationConflict::staleSnapshot();
        }

        $scopeHash = SchemaOperationAdmission::scopeHash($scope);

        return $this->connection->transaction(function () use ($scope, $scopeHash, $change, $key): SchemaReceipt {
            $this->current->assert($scope, $this->connection);
            $operations = $this->connection->table($this->tables->schemaOperations);
            $existing = $operations->where('operation_key', $key->key)->where('scope_hash', $scopeHash)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $key->payloadHash) {
                    throw new KeyConflict('A schema operation key is already bound to different data.');
                }

                return new SchemaReceipt(operation: new OperationRef(Value::text($existing->id)), status: Value::text($existing->status), schemaHash: Value::text($existing->schema_hash), errorCode: Value::nullableText($existing->error_code));
            }

            $id = hash('sha256', $scopeHash."\n".$key->key."\n".$key->payloadHash);
            $operations->insert([
                'id' => $id, 'scope_hash' => $scopeHash, 'operation_key' => $key->key, 'payload_hash' => $key->payloadHash,
                'entity' => $change->entity, 'schema_hash' => $change->expectedSchemaHash,
                'approval_digest' => $change->approvalDigest, 'restore_epoch' => $scope->restoreEpoch,
                'definition' => json_encode(['kind' => $change->kind, 'definition' => $change->definition], JSON_THROW_ON_ERROR),
                'status' => 'pending', 'error_code' => null,
            ]);

            return new SchemaReceipt(operation: new OperationRef($id), status: 'pending', schemaHash: $change->expectedSchemaHash, errorCode: null);
        });
    }

    public function inspect(DataScope $scope, OperationRef $operation): SchemaReceipt
    {
        $this->current->assert($scope, $this->connection);
        $row = $this->connection->table($this->tables->schemaOperations)->where('id', $operation->id)->where('scope_hash', SchemaOperationAdmission::scopeHash($scope))->first();

        if ($row === null || $row->restore_epoch !== $scope->restoreEpoch || ! $this->admission->allows($scope, Value::text($row->entity), FieldUse::Read)) {
            throw new ScopeDenied('The current scope cannot inspect this schema operation.');
        }

        return new SchemaReceipt(operation: $operation, status: Value::text($row->status), schemaHash: Value::text($row->schema_hash), errorCode: Value::nullableText($row->error_code));
    }

    public function verify(DataScope $scope, string $entity): DriftReport
    {
        $this->current->assert($scope, $this->connection);

        if (! $this->admission->allows($scope, $entity, FieldUse::Read)) {
            throw new ScopeDenied('The current scope cannot verify this schema.');
        }

        $rows = $this->connection->table($this->tables->schemaOperations)->where('entity', $entity)->where('scope_hash', SchemaOperationAdmission::scopeHash($scope))->oldest()->get();
        $drift = [];
        $managed = [];
        foreach ($rows as $row) {
            $definition = json_decode(Value::text($row->definition), true, 16, JSON_THROW_ON_ERROR);
            $definition = is_array($definition) ? $definition : [];
            $payload = $definition['definition'] ?? null;
            $field = is_array($payload) ? ($payload['field'] ?? null) : null;

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
            $exists = $this->connection->table('pg_indexes')->where('schemaname', 'public')->where('tablename', $this->tables->documents)->where('indexname', $index)->exists();

            if ($exists !== $expectsIndex) {
                $drift[] = ['field' => $field, 'reason' => $expectsIndex ? 'missing_managed_index' : 'unexpected_managed_index'];
            }
        }

        $schemaHash = $this->catalogue->snapshot(scope: $scope, entity: $entity)->schemaHash;

        $drift = Validation::validatedMapList(values: $drift, name: 'schema drift');

        return new DriftReport(entity: $entity, schemaHash: $schemaHash, drift: $drift, status: $drift === [] ? 'clean' : 'drifted');
    }
}
