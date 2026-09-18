<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Engine;

use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Data\AttributeTables;
use CoreX\Attributes\Internal\KeyConflict;
use CoreX\Attributes\Internal\ScopeDenied;
use CoreX\Attributes\Internal\VersionConflict;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValueBag;
use CoreX\Attributes\Support\ValueBatch;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Support\WriteResult;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use JsonException;
use RuntimeException;

final readonly class JsonbAttributeStore implements AttributeStore
{
    public function __construct(
        private ConnectionInterface $connection,
        private AttributeTables $tables,
        private FieldCatalogue $catalogue,
        private DataScopeAuthorizer $authorizer,
    ) {}

    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);
        $this->authorize($scope, $record, $fields->fields, 'read');
        $this->validator()->validateSelection(scope: $scope, record: $record, selection: $fields);
        $row = $this->documents()->where('entity', $record->entity)->where('record_id', $record->id)->first();
        $values = $row === null ? [] : $this->decode($row->values);

        return new ValueBag(values: array_intersect_key($values, array_fill_keys($fields->fields, true)));
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): WriteResult
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);
        $fields = array_keys($patch->values);
        $this->authorize($scope, $record, $fields, 'write');
        $this->validator()->validatePatch(scope: $scope, record: $record, patch: $patch);

        return $this->connection->transaction(function () use ($record, $patch, $token): WriteResult {
            $existing = $this->tokens()->where('operation_key', $token->operationKey)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $token->payloadHash || $existing->entity !== $record->entity || $existing->record_id !== $record->id) {
                    throw new KeyConflict('An operation key is already bound to different data.');
                }

                return new WriteResult(status: 'replayed', recordVersion: $existing->record_version, payloadHash: $existing->payload_hash);
            }

            $current = $this->documents()->where('entity', $record->entity)->where('record_id', $record->id)->lockForUpdate()->first();
            $currentVersion = $current === null ? 'absent' : $current->record_version;

            if ($token->expectedRecordVersion !== $currentVersion) {
                throw new VersionConflict('The attribute record version is stale.');
            }

            $values = $current === null ? [] : $this->decode($current->values);
            foreach ($patch->values as $field => $value) {
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

            return new WriteResult(status: 'applied', recordVersion: $version, payloadHash: $token->payloadHash);
        });
    }

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);
        foreach ($records->records as $record) {
            $this->authorize($scope, $record, $fields->fields, 'read');
            $this->validator()->validateSelection(scope: $scope, record: $record, selection: $fields);
        }

        $rows = $this->documents()->where(function ($query) use ($records): void {
            foreach ($this->recordsByEntity($records) as $entity => $ids) {
                $query->orWhere(function ($entityQuery) use ($entity, $ids): void {
                    $entityQuery->where('entity', $entity)->whereIn('record_id', $ids);
                });
            }
        })->get();

        $valuesByRecord = [];
        foreach ($rows as $row) {
            $valuesByRecord[$row->entity."\n".$row->record_id] = $this->decode($row->values);
        }

        return new ValueBatch(records: array_map(
            fn (RecordRef $record): ValueBag => new ValueBag(values: array_intersect_key(
                $valuesByRecord[$record->entity."\n".$record->id] ?? [],
                array_fill_keys($fields->fields, true),
            )),
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

        return $values;
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
}
