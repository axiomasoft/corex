<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Engine;

use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Internal\KeyConflict;
use CoreX\Attributes\Internal\ScopeDenied;
use CoreX\Attributes\Internal\VersionConflict;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValueBag;
use CoreX\Attributes\Support\ValueBatch;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Support\WriteResult;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;
use Illuminate\Database\ConnectionInterface;
use JsonException;
use RuntimeException;

final readonly class EavAttributeStore implements AttributeStore
{
    public function __construct(
        private ConnectionInterface $connection,
        private ?FieldCatalogue $catalogue = null,
    ) {}

    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);
        $this->authorize($scope, $record, $fields->fields, 'read');
        $this->validator()?->validateSelection(scope: $scope, record: $record, selection: $fields);
        $rows = $this->connection->table('sys_attribute_eav_values')->where('entity', $record->entity)->where('record_id', $record->id)->whereIn('field', $fields->fields)->get();
        $values = [];
        foreach ($rows as $row) {
            $values[$row->field] = $this->decode($row->value);
        }

        return new ValueBag(values: $values);
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): WriteResult
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);
        $fields = array_keys($patch->values);
        $this->authorize($scope, $record, $fields, 'write');
        $this->validator()?->validatePatch(scope: $scope, record: $record, patch: $patch);

        return $this->connection->transaction(function () use ($record, $patch, $token): WriteResult {
            $tokens = $this->connection->table('sys_attribute_write_tokens');
            $existing = $tokens->where('operation_key', $token->operationKey)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $token->payloadHash || $existing->entity !== $record->entity || $existing->record_id !== $record->id) {
                    throw new KeyConflict('An operation key is already bound to different data.');
                }

                return new WriteResult(status: 'replayed', recordVersion: $existing->record_version, payloadHash: $existing->payload_hash);
            }

            $records = $this->connection->table('sys_attribute_eav_records');
            $current = $records->where('entity', $record->entity)->where('record_id', $record->id)->lockForUpdate()->first();
            $currentVersion = $current === null ? 'absent' : $current->record_version;

            if ($currentVersion !== $token->expectedRecordVersion) {
                throw new VersionConflict('The attribute record version is stale.');
            }

            $version = hash('sha256', $token->operationKey."\n".$token->payloadHash."\n".$currentVersion);
            foreach ($patch->values as $field => $value) {
                $this->connection->table('sys_attribute_eav_values')->updateOrInsert(
                    ['entity' => $record->entity, 'record_id' => $record->id, 'field' => $field],
                    ['value' => $this->encode($value)],
                );
            }

            $records->updateOrInsert(['entity' => $record->entity, 'record_id' => $record->id], ['record_version' => $version]);
            $tokens->insert(['operation_key' => $token->operationKey, 'entity' => $record->entity, 'record_id' => $record->id, 'payload_hash' => $token->payloadHash, 'record_version' => $version]);

            return new WriteResult(status: 'applied', recordVersion: $version, payloadHash: $token->payloadHash);
        });
    }

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch
    {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);
        foreach ($records->records as $record) {
            $this->authorize($scope, $record, $fields->fields, 'read');
            $this->validator()?->validateSelection(scope: $scope, record: $record, selection: $fields);
        }

        $rows = $this->connection->table('sys_attribute_eav_values')
            ->whereIn('field', $fields->fields)
            ->where(function ($query) use ($records): void {
                foreach ($this->recordsByEntity($records) as $entity => $ids) {
                    $query->orWhere(function ($entityQuery) use ($entity, $ids): void {
                        $entityQuery->where('entity', $entity)->whereIn('record_id', $ids);
                    });
                }
            })
            ->get();

        $valuesByRecord = [];
        foreach ($rows as $row) {
            $valuesByRecord[$row->entity."\n".$row->record_id][$row->field] = $this->decode($row->value);
        }

        return new ValueBatch(records: array_map(
            static fn (RecordRef $record): ValueBag => new ValueBag(values: $valuesByRecord[$record->entity."\n".$record->id] ?? []),
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
        if (! ($scope->authorize)($record->entity, $record->id, $fields, $action)) {
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

    private function validator(): ?FieldValueValidator
    {
        return $this->catalogue === null ? null : new FieldValueValidator($this->catalogue);
    }
}
