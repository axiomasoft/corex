<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Values;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Data\AttributeWriteResult;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\ValueBag;
use CoreX\CustomFields\Data\ValueBatch;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator;
use CoreX\CustomFields\Internal\Integration\AttributeValuesChanged;
use CoreX\CustomFields\Internal\KeyConflict;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Support\Validation;
use CoreX\CustomFields\Internal\Value;
use CoreX\CustomFields\Internal\VersionConflict;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Enums\FieldUse;
use CoreX\Messaging\Contracts\Outbox;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use JsonException;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;

#[Internal]
final readonly class EavAttributeStore implements AttributeStore
{
    public function __construct(
        private ConnectionInterface $connection,
        private CurrentWorkScope $current,
        private AttributeTables $tables,
        private FieldCatalogue $catalogue,
        private DataScopeAuthorizer $authorizer,
        private ?AttributeValueTransformerRegistry $transformers = null,
        private ?Outbox $outbox = null,
        private ?AttributeMessageTranslator $messageTranslator = null,
        private ?ClockInterface $clock = null,
    ) {}

    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
    {
        $this->current->assert($scope, $this->connection);
        $this->authorize(scope: $scope, record: $record, fields: $fields);
        $definitions = $this->validator()->selectedDefinitions(scope: $scope, entity: $record->entity, selection: $fields);
        $physicalFields = $this->physicalReadKeys($definitions);
        $rows = $this->connection->table($this->tables->eavValues)->where('entity', $record->entity)->where('record_id', $record->id)->whereIn('field', $physicalFields)->get();
        $values = [];
        foreach ($rows as $row) {
            $values[Value::text($row->field)] = $this->decode(Value::text($row->value));
        }

        $values = $this->projectValues(definitions: $definitions, stored: Value::map($values));
        $values = Validation::validatedMap(value: $values, name: 'attribute values');

        return new ValueBag(values: $values);
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): AttributeWriteResult
    {
        $this->current->assert($scope, $this->connection);
        $fields = array_keys($patch->values);
        $snapshot = $this->writeGuard()->assertCanWrite(scope: $scope, record: $record, patch: $patch);
        $definitions = $this->definitionsFromSnapshot(snapshot: $snapshot, fields: $fields);

        return $this->connection->transaction(function () use ($scope, $record, $patch, $token, $snapshot, $definitions): AttributeWriteResult {
            $this->current->assert($scope, $this->connection);
            $tokens = $this->connection->table($this->tables->writeTokens);
            $existing = $tokens->where('operation_key', $token->operationKey)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $token->payloadHash || $existing->entity !== $record->entity || $existing->record_id !== $record->id) {
                    throw new KeyConflict('An operation key is already bound to different data.');
                }

                return new AttributeWriteResult(status: 'replayed', recordVersion: Value::text($existing->record_version), payloadHash: $existing->payload_hash);
            }

            $records = $this->connection->table($this->tables->eavRecords);
            $current = $records->where('entity', $record->entity)->where('record_id', $record->id)->lockForUpdate()->first();
            $currentVersion = $current === null ? 'absent' : $current->record_version;

            if ($currentVersion !== $token->expectedRecordVersion) {
                throw new VersionConflict('The attribute record version is stale.');
            }

            $existingValues = $this->connection->table($this->tables->eavValues)
                ->where('entity', $record->entity)
                ->where('record_id', $record->id)
                ->whereIn('field', $this->physicalReadKeys($definitions))
                ->pluck('value', 'field')
                ->map(fn (mixed $value): mixed => $this->decode(Value::text($value)))
                ->all();
            $this->assertNoDrift(definitions: $definitions, stored: Value::map($existingValues));

            $version = hash('sha256', $token->operationKey."\n".$token->payloadHash."\n".$currentVersion);
            foreach ($this->expandPatch(definitions: $definitions, patch: $patch->values) as $field => $value) {
                $this->connection->table($this->tables->eavValues)->updateOrInsert(
                    ['entity' => $record->entity, 'record_id' => $record->id, 'field' => $field],
                    ['value' => $this->encode($value)],
                );
            }

            $records->updateOrInsert(['entity' => $record->entity, 'record_id' => $record->id], ['record_version' => $version]);
            $tokens->insert(['operation_key' => $token->operationKey, 'entity' => $record->entity, 'record_id' => $record->id, 'payload_hash' => $token->payloadHash, 'record_version' => $version]);

            $this->appendMutationMessage(
                scope: $scope,
                record: $record,
                recordVersion: $version,
                schemaHash: $snapshot->schemaHash,
                definitions: $definitions,
                correlationId: $token->operationKey,
            );

            return new AttributeWriteResult(status: 'applied', recordVersion: $version, payloadHash: $token->payloadHash);
        });
    }

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch
    {
        $this->current->assert($scope, $this->connection);
        foreach ($records->records as $record) {
            $this->authorize(scope: $scope, record: $record, fields: $fields);
        }
        $definitionsByEntity = [];
        $physicalFields = [];
        foreach ($this->recordsByEntity($records) as $entity => $_ids) {
            $definitionsByEntity[$entity] = $this->validator()->selectedDefinitions(scope: $scope, entity: $entity, selection: $fields);
            $physicalFields = array_merge($physicalFields, $this->physicalReadKeys($definitionsByEntity[$entity]));
        }

        $rows = $this->connection->table($this->tables->eavValues)
            ->whereIn('field', array_values(array_unique($physicalFields)))
            ->where(function (Builder $query) use ($records): void {
                foreach ($this->recordsByEntity($records) as $entity => $ids) {
                    $query->orWhere(function (Builder $entityQuery) use ($entity, $ids): void {
                        $entityQuery->where('entity', $entity)->whereIn('record_id', $ids);
                    });
                }
            })
            ->get();

        $valuesByRecord = [];
        foreach ($rows as $row) {
            $valuesByRecord[Value::text($row->entity)."\n".Value::text($row->record_id)][Value::text($row->field)] = $this->decode(Value::text($row->value));
        }

        return new ValueBatch(records: array_map(
            fn (RecordRef $record): ValueBag => new ValueBag(values: Validation::validatedMap(value: $this->projectValues(
                definitions: $definitionsByEntity[$record->entity],
                stored: Value::map($valuesByRecord[$record->entity."\n".$record->id] ?? []),
            ), name: 'attribute values')),
            $records->records,
        ));
    }

    /** @return array<string, list<string>> */
    private function recordsByEntity(RecordSelection $records): array
    {
        $grouped = [];
        foreach ($records->records as $record) {
            $grouped[$record->entity] ??= [];
            $grouped[$record->entity][] = $record->id;
        }

        return $grouped;
    }

    private function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields): void
    {
        if (! $this->authorizer->authorize($scope, $record, new FieldSelection([]), FieldUse::Read)->allowed
            || ($fields->fields !== [] && ! $this->authorizer->authorize($scope, $record, $fields, FieldUse::Read)->allowed)) {
            throw new ScopeDenied('The current scope denies this attribute operation.');
        }
    }

    private function decode(string $json): mixed
    {
        try {
            return json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Stored attribute value is invalid JSON.', previous: $exception);
        }
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Attribute value cannot be encoded.', previous: $exception);
        }
    }

    private function validator(): FieldValueValidator
    {
        return new FieldValueValidator($this->catalogue);
    }

    private function writeGuard(): AttributeWriteGuard
    {
        return new AttributeWriteGuard(
            catalogue: $this->catalogue,
            authorizer: $this->authorizer,
            validator: $this->validator(),
        );
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function definitionsFromSnapshot(FieldSnapshot $snapshot, array $fields): array
    {
        $requested = array_fill_keys($fields, true);
        $definitions = [];
        foreach ($snapshot->activeFields as $definition) {
            $key = $definition['code'] ?? $definition['key'] ?? null;

            if (is_string($key) && isset($requested[$key])) {
                $definitions[$key] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @return list<string>
     */
    private function physicalReadKeys(array $definitions): array
    {
        $keys = [];
        foreach ($definitions as $logicalKey => $definition) {
            $keys = array_merge($keys, $this->readKeys(logicalKey: $logicalKey, definition: $definition));
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function projectValues(array $definitions, array $stored): array
    {
        $projected = [];
        foreach ($definitions as $logicalKey => $definition) {
            foreach ($this->readKeys(logicalKey: $logicalKey, definition: $definition) as $physicalKey) {
                if (! array_key_exists($physicalKey, $stored)) {
                    continue;
                }

                $projected[$logicalKey] = $this->toLogicalValue(definition: $definition, physicalKey: $physicalKey, value: $stored[$physicalKey]);

                break;
            }
        }

        return $projected;
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function expandPatch(array $definitions, array $patch): array
    {
        $expanded = [];
        foreach ($patch as $logicalKey => $value) {
            $definition = $definitions[$logicalKey] ?? ['key' => $logicalKey];
            foreach ($this->writeKeys(logicalKey: $logicalKey, definition: $definition) as $physicalKey) {
                $physicalValue = $this->toPhysicalValue(definition: $definition, physicalKey: $physicalKey, value: $value);

                if (array_key_exists($physicalKey, $expanded) && ! $this->sameValue(left: $expanded[$physicalKey], right: $physicalValue)) {
                    throw new KeyConflict("Attribute writes disagree on physical key [{$physicalKey}].");
                }
                $expanded[$physicalKey] = $physicalValue;
            }
        }

        return $expanded;
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $stored
     */
    private function assertNoDrift(array $definitions, array $stored): void
    {
        foreach ($definitions as $logicalKey => $definition) {
            $canonical = null;
            $found = false;
            foreach ($this->readKeys(logicalKey: $logicalKey, definition: $definition) as $physicalKey) {
                if (! array_key_exists($physicalKey, $stored)) {
                    continue;
                }

                $value = $this->toLogicalValue(definition: $definition, physicalKey: $physicalKey, value: $stored[$physicalKey]);

                if ($found && ! $this->sameValue(left: $canonical, right: $value)) {
                    throw new VersionConflict("Attribute migration values diverged for [{$logicalKey}].");
                }
                $canonical = $value;
                $found = true;
            }
        }
    }

    /** @param array<string, mixed> $definition
     * @return list<string>
     */
    private function readKeys(string $logicalKey, array $definition): array
    {
        $keys = $definition['read_keys'] ?? [];

        return is_array($keys) && $keys !== [] ? Value::strings(array_values($keys)) : [$logicalKey];
    }

    /** @param array<string, mixed> $definition
     * @return list<string>
     */
    private function writeKeys(string $logicalKey, array $definition): array
    {
        $keys = $definition['write_keys'] ?? [];

        return is_array($keys) && $keys !== [] ? Value::strings(array_values($keys)) : [$logicalKey];
    }

    /** @param array<string, mixed> $definition */
    private function toLogicalValue(array $definition, string $physicalKey, mixed $value): mixed
    {
        $meta = is_array($definition['meta'] ?? null) ? $definition['meta'] : [];

        return ($meta['evolution_source_key'] ?? null) === $physicalKey
            ? $this->transformerRegistry()->toTarget(meta: Value::map($meta), value: $value)
            : $value;
    }

    /** @param array<string, mixed> $definition */
    private function toPhysicalValue(array $definition, string $physicalKey, mixed $value): mixed
    {
        $meta = is_array($definition['meta'] ?? null) ? $definition['meta'] : [];

        return ($meta['evolution_source_key'] ?? null) === $physicalKey
            ? $this->transformerRegistry()->toSource(meta: Value::map($meta), value: $value)
            : $value;
    }

    private function transformerRegistry(): AttributeValueTransformerRegistry
    {
        return $this->transformers ?? new AttributeValueTransformerRegistry;
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        return json_encode($left, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)
            === json_encode($right, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** @param array<string, array<string, mixed>> $definitions */
    private function appendMutationMessage(
        DataScope $scope,
        RecordRef $record,
        string $recordVersion,
        string $schemaHash,
        array $definitions,
        string $correlationId,
    ): void {
        if ($this->outbox === null) {
            return;
        }

        if ($this->messageTranslator === null || $this->clock === null) {
            throw new LogicException('Attribute outbox wiring requires a translator and clock.');
        }

        $ids = [];
        foreach ($definitions as $key => $definition) {
            $id = $definition['id'] ?? null;
            $ids[] = is_string($id) && $id !== ''
                ? $id
                : AttributeId::legacy(entity: $record->entity, key: $key)->value;
        }

        $this->outbox->append($this->messageTranslator->translate(new AttributeValuesChanged(
            messageId: (string) Str::uuid7(),
            scope: $scope,
            recordEntity: $record->entity,
            recordId: $record->id,
            recordVersion: $recordVersion,
            schemaHash: $schemaHash,
            changedAttributeIds: $ids,
            changedKeys: array_keys($definitions),
            occurredAt: $this->clock->now(),
            correlationId: $correlationId,
        )));
    }
}
