<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Values;

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Data\AttributeWriteResult;
use CoreX\CustomFields\Data\FieldSelection;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\ValueBag;
use CoreX\CustomFields\Data\ValueBatch;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator;
use CoreX\CustomFields\Internal\Integration\AttributeValuesChanged;
use CoreX\CustomFields\Internal\KeyConflict;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Storage\ScopeConnectionGuard;
use CoreX\CustomFields\Internal\Support\Validation;
use CoreX\CustomFields\Internal\Value;
use CoreX\CustomFields\Internal\VersionConflict;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Messaging\Contracts\Outbox;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use JsonException;
use LogicException;
use Psr\Clock\ClockInterface;
use RuntimeException;

final readonly class JsonbAttributeStore implements AttributeStore
{
    public function __construct(
        private ConnectionInterface $connection,
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
        new ScopeConnectionGuard($this->connection)->assertMatches($scope);
        $this->authorize(scope: $scope, record: $record, fields: $fields->fields, action: 'read');
        $this->validator()->validateSelection(scope: $scope, record: $record, selection: $fields);
        $definitions = $this->definitions(scope: $scope, entity: $record->entity, fields: $fields->fields);
        $row = $this->documents()->where('entity', $record->entity)->where('record_id', $record->id)->first();
        $values = $row === null ? [] : $this->decode(Value::text($row->values));

        $values = $this->projectValues(definitions: $definitions, stored: $values);
        $values = Validation::validatedMap(value: $values, name: 'attribute values');

        return new ValueBag(values: $values);
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): AttributeWriteResult
    {
        new ScopeConnectionGuard($this->connection)->assertMatches($scope);
        $fields = array_keys($patch->values);
        $snapshot = $this->writeGuard()->assertCanWrite(scope: $scope, record: $record, patch: $patch);
        $definitions = $this->definitionsFromSnapshot(snapshot: $snapshot, fields: $fields);

        return $this->connection->transaction(function () use ($scope, $record, $patch, $token, $snapshot, $definitions): AttributeWriteResult {
            $existing = $this->tokens()->where('operation_key', $token->operationKey)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $token->payloadHash || $existing->entity !== $record->entity || $existing->record_id !== $record->id) {
                    throw new KeyConflict('An operation key is already bound to different data.');
                }

                return new AttributeWriteResult(status: 'replayed', recordVersion: Value::text($existing->record_version), payloadHash: $existing->payload_hash);
            }

            $current = $this->documents()->where('entity', $record->entity)->where('record_id', $record->id)->lockForUpdate()->first();
            $currentVersion = $current === null ? 'absent' : $current->record_version;

            if ($token->expectedRecordVersion !== $currentVersion) {
                throw new VersionConflict('The attribute record version is stale.');
            }

            $values = $current === null ? [] : $this->decode(Value::text($current->values));
            $this->assertNoDrift(definitions: $definitions, stored: $values);
            foreach ($this->expandPatch(definitions: $definitions, patch: $patch->values) as $field => $value) {
                $values[$field] = $value;
            }

            $version = hash('sha256', $token->operationKey."\n".$token->payloadHash."\n".$currentVersion);
            $payload = $this->encode($values);

            if ($current === null) {
                $this->documents()->insert([
                    'entity' => $record->entity,
                    'record_id' => $record->id,
                    'values' => $payload,
                    'record_version' => $version,
                ]);
            } else {
                $this->documents()->where('entity', $record->entity)->where('record_id', $record->id)->update([
                    'values' => $payload,
                    'record_version' => $version,
                ]);
            }

            $this->tokens()->insert([
                'operation_key' => $token->operationKey,
                'entity' => $record->entity,
                'record_id' => $record->id,
                'payload_hash' => $token->payloadHash,
                'record_version' => $version,
            ]);

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
        new ScopeConnectionGuard($this->connection)->assertMatches($scope);
        foreach ($records->records as $record) {
            $this->authorize(scope: $scope, record: $record, fields: $fields->fields, action: 'read');
            $this->validator()->validateSelection(scope: $scope, record: $record, selection: $fields);
        }
        $definitionsByEntity = [];
        foreach ($this->recordsByEntity($records) as $entity => $_ids) {
            $definitionsByEntity[$entity] = $this->definitions(scope: $scope, entity: $entity, fields: $fields->fields);
        }

        $rows = $this->documents()->where(function (Builder $query) use ($records): void {
            foreach ($this->recordsByEntity($records) as $entity => $ids) {
                $query->orWhere(function (Builder $entityQuery) use ($entity, $ids): void {
                    $entityQuery->where('entity', $entity)->whereIn('record_id', $ids);
                });
            }
        })->get();

        $valuesByRecord = [];
        foreach ($rows as $row) {
            $valuesByRecord[Value::text($row->entity)."\n".Value::text($row->record_id)] = $this->decode(Value::text($row->values));
        }

        return new ValueBatch(records: array_map(
            fn (RecordRef $record): ValueBag => new ValueBag(values: Validation::validatedMap(value: $this->projectValues(
                definitions: $definitionsByEntity[$record->entity],
                stored: $valuesByRecord[$record->entity."\n".$record->id] ?? [],
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

    /** @param list<string> $fields */
    private function authorize(DataScope $scope, RecordRef $record, array $fields, string $action): void
    {
        if (! $this->authorizer->authorize(scope: $scope, entity: $record->entity, subjectId: $record->id, fields: $fields, action: $action)->allowed) {
            throw new ScopeDenied('The current scope denies this attribute operation.');
        }
    }

    private function documents(): Builder
    {
        return $this->connection->table($this->tables->documents);
    }

    private function tokens(): Builder
    {
        return $this->connection->table($this->tables->writeTokens);
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        try {
            $values = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Stored attribute document is invalid JSON.', previous: $exception);
        }

        if (! is_array($values) || array_is_list($values)) {
            throw new RuntimeException('Stored attribute document must be a JSON object.');
        }

        return Value::map($values);
    }

    /** @param array<string, mixed> $values */
    private function encode(array $values): string
    {
        try {
            return json_encode($values, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Attribute document cannot be encoded.', previous: $exception);
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
    private function definitions(DataScope $scope, string $entity, array $fields): array
    {
        return $this->definitionsFromSnapshot(snapshot: $this->catalogue->snapshot(scope: $scope, entity: $entity), fields: $fields);
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
