<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Values;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Internal\KeyConflict;
use CoreX\CustomFields\Internal\Value;
use CoreX\CustomFields\Internal\VersionConflict;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Internal;
use RuntimeException;
use stdClass;

#[Internal]
final readonly class AttributeBackfill
{
    public function __construct(
        private ConnectionInterface $connection,
        private CurrentWorkScope $current,
        private AttributeTables $tables,
        private AttributeValueTransformerRegistry $transformers,
        private string $engine,
    ) {
        if (! in_array($this->engine, ['jsonb', 'eav'], true)) {
            throw new InvalidArgumentException('Attribute backfill engine must be jsonb or eav.');
        }
    }

    public function start(DataScope $scope, string $operationId, AttributeDefinition $from, AttributeDefinition $to): void
    {
        $this->current->assert($scope, $this->connection);

        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $operationId) !== 1) {
            throw new InvalidArgumentException('Attribute backfill operation id must be a UUID.');
        }

        if ($from->id->value !== $to->id->value || $from->entity !== $to->entity || $to->revision <= $from->revision) {
            throw new InvalidArgumentException('Attribute backfill revisions must advance one attribute identity.');
        }

        if ($to->lifecycle !== AttributeLifecycle::Migrating) {
            throw new InvalidArgumentException('Attribute backfill target must be migrating.');
        }

        if (($to->meta['evolution_source_key'] ?? null) !== $from->key
            || ($to->meta['evolution_source_type'] ?? null) !== $from->type
            || ($to->meta['evolution_target_key'] ?? null) !== $to->key
            || ($to->meta['evolution_target_type'] ?? null) !== $to->type) {
            throw new InvalidArgumentException('Attribute backfill target metadata does not match its revisions.');
        }

        $payload = [
            'attribute_id' => $to->id->value,
            'from_revision' => $from->revision,
            'to_revision' => $to->revision,
            'entity' => $to->entity,
            'source_key' => $from->key,
            'target_key' => $to->key,
        ];

        $this->connection->transaction(function () use ($scope, $operationId, $payload): void {
            $this->current->assert($scope, $this->connection);
            $existing = $this->backfills()->where('id', $operationId)->lockForUpdate()->first();

            if ($existing !== null) {
                foreach ($payload as $column => $value) {
                    if (Value::text($existing->{$column}) !== Value::text($value)) {
                        throw new KeyConflict('A backfill operation id is already bound to different revisions.');
                    }
                }

                return;
            }

            $this->backfills()->insert(array_merge($payload, [
                'id' => $operationId,
                'status' => 'pending',
                'attempted' => 0,
                'changed' => 0,
                'failed' => 0,
                'created_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
                'updated_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
            ]));
        });
    }

    /** @return array{status: string, attempted: int, changed: int, cursor: ?string} */
    public function runBatch(DataScope $scope, string $operationId, int $batchSize = 100): array
    {
        $this->current->assert($scope, $this->connection);

        if ($batchSize < 1 || $batchSize > 1000) {
            throw new InvalidArgumentException('Attribute backfill batch size must be between 1 and 1000.');
        }

        return $this->connection->transaction(function () use ($scope, $operationId, $batchSize): array {
            $this->current->assert($scope, $this->connection);
            $operation = $this->operationForUpdate($operationId);

            if ($operation->status === 'aborted') {
                throw new RuntimeException('An aborted attribute backfill cannot resume.');
            }

            if ($operation->status === 'verified') {
                return $this->batchReceipt($operation);
            }

            $records = $this->recordRows(operation: $operation, batchSize: $batchSize);

            if ($records === []) {
                $this->backfills()->where('id', $operationId)->update([
                    'status' => 'backfilled',
                    'error' => null,
                    'updated_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
                ]);
                $operation->status = 'backfilled';

                return $this->batchReceipt($operation);
            }

            $meta = $this->targetMeta($operation);
            $attempted = 0;
            $changed = 0;
            foreach ($records as $selected) {
                $attempted++;
                $changed += $this->backfillRecord(operation: $operation, selected: $selected, meta: $meta) ? 1 : 0;
            }

            $cursor = Value::text(end($records)->record_id);
            $this->backfills()->where('id', $operationId)->update([
                'status' => 'backfilling',
                'cursor_record_id' => $cursor,
                'attempted' => Value::integer($operation->attempted) + $attempted,
                'changed' => Value::integer($operation->changed) + $changed,
                'error' => null,
                'updated_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
            ]);

            return [
                'status' => 'backfilling',
                'attempted' => Value::integer($operation->attempted) + $attempted,
                'changed' => Value::integer($operation->changed) + $changed,
                'cursor' => $cursor,
            ];
        });
    }

    /** @return array{status: string, source_digest: string, target_digest: string} */
    public function verify(DataScope $scope, string $operationId): array
    {
        $this->current->assert($scope, $this->connection);

        $result = $this->connection->transaction(function () use ($scope, $operationId): array {
            $this->current->assert($scope, $this->connection);
            $operation = $this->operationForUpdate($operationId);

            if ($operation->status !== 'backfilled' && $operation->status !== 'verified') {
                throw new RuntimeException('Attribute backfill must finish before verification.');
            }

            $meta = $this->targetMeta($operation);
            [$sourceDigest, $targetDigest, $failed] = $this->digests(operation: $operation, meta: $meta);
            $status = $failed === 0 && hash_equals($sourceDigest, $targetDigest) ? 'verified' : 'backfilled';
            $this->backfills()->where('id', $operationId)->update([
                'status' => $status,
                'source_digest' => $sourceDigest,
                'target_digest' => $targetDigest,
                'failed' => $failed,
                'error' => $failed === 0 ? null : 'digest_mismatch',
                'updated_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
            ]);

            return [
                'status' => $status,
                'source_digest' => $sourceDigest,
                'target_digest' => $targetDigest,
                'failed' => $failed,
            ];
        });

        if ($result['failed'] !== 0) {
            throw new RuntimeException('Attribute backfill verification found missing or divergent values.');
        }

        return [
            'status' => $result['status'],
            'source_digest' => $result['source_digest'],
            'target_digest' => $result['target_digest'],
        ];
    }

    public function abort(DataScope $scope, string $operationId): void
    {
        $this->current->assert($scope, $this->connection);
        $this->connection->transaction(function () use ($scope, $operationId): void {
            $this->current->assert($scope, $this->connection);
            $operation = $this->operationForUpdate($operationId);

            if ($operation->status === 'verified') {
                throw new RuntimeException('A verified attribute backfill cannot be aborted.');
            }

            $this->backfills()->where('id', $operationId)->update([
                'status' => 'aborted',
                'updated_at' => $this->connection->raw('CURRENT_TIMESTAMP'),
            ]);
        });
    }

    /** @return list<stdClass> */
    private function recordRows(stdClass $operation, int $batchSize): array
    {
        $table = $this->engine === 'jsonb' ? $this->tables->documents : $this->tables->eavRecords;
        $query = $this->connection->table($table)
            ->where('entity', $operation->entity)
            ->orderBy('record_id')
            ->limit($batchSize);

        if ($operation->cursor_record_id !== null) {
            $query->where('record_id', '>', $operation->cursor_record_id);
        }

        /** @var list<stdClass> $rows */
        $rows = $query->get(['record_id', 'record_version'])->all();

        return $rows;
    }

    /** @param array<string, mixed> $meta */
    private function backfillRecord(stdClass $operation, stdClass $selected, array $meta): bool
    {
        if ($this->engine === 'jsonb') {
            return $this->backfillJsonbRecord(operation: $operation, selected: $selected, meta: $meta);
        }

        return $this->backfillEavRecord(operation: $operation, selected: $selected, meta: $meta);
    }

    /** @param array<string, mixed> $meta */
    private function backfillJsonbRecord(stdClass $operation, stdClass $selected, array $meta): bool
    {
        $record = $this->connection->table($this->tables->documents)
            ->where('entity', $operation->entity)
            ->where('record_id', $selected->record_id)
            ->lockForUpdate()
            ->first();
        $this->assertRecordVersion(selected: $selected, current: $record);
        $values = $this->decodeMap(Value::text($record->values));

        if (! array_key_exists(Value::text($operation->source_key), $values)) {
            return false;
        }

        $expected = $this->transformers->toTarget(meta: $meta, value: $values[Value::text($operation->source_key)]);

        if (array_key_exists(Value::text($operation->target_key), $values)) {
            if (! $this->sameValue(left: $expected, right: $values[Value::text($operation->target_key)])) {
                throw new RuntimeException('Concurrent attribute values diverged during backfill.');
            }

            return false;
        }

        $values[Value::text($operation->target_key)] = $expected;
        $this->connection->table($this->tables->documents)
            ->where('entity', $operation->entity)
            ->where('record_id', $selected->record_id)
            ->update([
                'values' => $this->encode($values),
                'record_version' => $this->backfillVersion(operation: $operation, selected: $selected),
            ]);

        return true;
    }

    /** @param array<string, mixed> $meta */
    private function backfillEavRecord(stdClass $operation, stdClass $selected, array $meta): bool
    {
        $record = $this->connection->table($this->tables->eavRecords)
            ->where('entity', $operation->entity)
            ->where('record_id', $selected->record_id)
            ->lockForUpdate()
            ->first();
        $this->assertRecordVersion(selected: $selected, current: $record);
        $values = $this->connection->table($this->tables->eavValues)
            ->where('entity', $operation->entity)
            ->where('record_id', $selected->record_id)
            ->whereIn('field', [Value::text($operation->source_key), Value::text($operation->target_key)])
            ->pluck('value', 'field')
            ->all();

        if (! array_key_exists(Value::text($operation->source_key), $values)) {
            return false;
        }

        $expected = $this->transformers->toTarget(meta: $meta, value: $this->decode(Value::text($values[Value::text($operation->source_key)])));

        if (array_key_exists(Value::text($operation->target_key), $values)) {
            if (! $this->sameValue(left: $expected, right: $this->decode(Value::text($values[Value::text($operation->target_key)])))) {
                throw new RuntimeException('Concurrent attribute values diverged during backfill.');
            }

            return false;
        }

        $this->connection->table($this->tables->eavValues)->insert([
            'entity' => $operation->entity,
            'record_id' => $selected->record_id,
            'field' => Value::text($operation->target_key),
            'value' => $this->encode($expected),
        ]);
        $this->connection->table($this->tables->eavRecords)
            ->where('entity', $operation->entity)
            ->where('record_id', $selected->record_id)
            ->update(['record_version' => $this->backfillVersion(operation: $operation, selected: $selected)]);

        return true;
    }

    /** @param array<string, mixed> $meta
     * @return array{string, string, int}
     */
    private function digests(stdClass $operation, array $meta): array
    {
        $expected = [];
        $actual = [];
        $failed = 0;
        foreach ($this->allRecordValues($operation) as $recordId => $values) {
            if (! array_key_exists(Value::text($operation->source_key), $values)) {
                continue;
            }

            $target = $this->transformers->toTarget(meta: $meta, value: $values[Value::text($operation->source_key)]);
            $expected[$recordId] = $target;

            if (! array_key_exists(Value::text($operation->target_key), $values)) {
                $failed++;

                continue;
            }

            $actual[$recordId] = $values[Value::text($operation->target_key)];

            if (! $this->sameValue(left: $target, right: $actual[$recordId])) {
                $failed++;
            }
        }

        return [$this->digest($expected), $this->digest($actual), $failed];
    }

    /** @return array<string, array<string, mixed>> */
    private function allRecordValues(stdClass $operation): array
    {
        $values = [];

        if ($this->engine === 'jsonb') {
            foreach ($this->connection->table($this->tables->documents)->where('entity', $operation->entity)->orderBy('record_id')->get() as $row) {
                $values[Value::text($row->record_id)] = $this->decodeMap(Value::text($row->values));
            }

            return $values;
        }

        foreach ($this->connection->table($this->tables->eavValues)
            ->where('entity', $operation->entity)
            ->whereIn('field', [Value::text($operation->source_key), Value::text($operation->target_key)])
            ->orderBy('record_id')
            ->orderBy('field')
            ->get() as $row) {
            $values[Value::text($row->record_id)][Value::text($row->field)] = $this->decode(Value::text($row->value));
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private function targetMeta(stdClass $operation): array
    {
        $definition = $this->connection->table($this->tables->definitions)
            ->where('attribute_id', $operation->attribute_id)
            ->where('revision', $operation->to_revision)
            ->first();

        if ($definition === null) {
            throw new RuntimeException('Attribute backfill target definition is missing.');
        }

        return $this->decodeMap(Value::text($definition->meta));
    }

    private function operationForUpdate(string $operationId): stdClass
    {
        $operation = $this->backfills()->where('id', $operationId)->lockForUpdate()->first();

        if (! $operation instanceof stdClass) {
            throw new RuntimeException('Attribute backfill operation was not found.');
        }

        return $operation;
    }

    /** @phpstan-assert stdClass $current */
    private function assertRecordVersion(stdClass $selected, ?stdClass $current): void
    {
        if ($current === null || $current->record_version !== $selected->record_version) {
            throw new VersionConflict('The attribute record changed before its backfill lock was acquired.');
        }
    }

    private function backfillVersion(stdClass $operation, stdClass $selected): string
    {
        return hash('sha256', Value::text($operation->id)."\n".Value::text($operation->target_key)."\n".Value::text($selected->record_version));
    }

    /** @return array{status: string, attempted: int, changed: int, cursor: ?string} */
    private function batchReceipt(stdClass $operation): array
    {
        return [
            'status' => Value::text($operation->status),
            'attempted' => Value::integer($operation->attempted),
            'changed' => Value::integer($operation->changed),
            'cursor' => Value::nullableText($operation->cursor_record_id),
        ];
    }

    private function backfills(): Builder
    {
        return $this->connection->table($this->tables->backfills);
    }

    /** @return array<string, mixed> */
    private function decodeMap(string $json): array
    {
        $decoded = $this->decode($json);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('Stored attribute backfill data must be a JSON object.');
        }

        return Value::map($decoded);
    }

    private function decode(string $json): mixed
    {
        try {
            return json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Stored attribute backfill data is invalid JSON.', previous: $exception);
        }
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new RuntimeException('Attribute backfill value cannot be encoded.', previous: $exception);
        }
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        return hash_equals($this->digest($left), $this->digest($right));
    }

    private function digest(mixed $value): string
    {
        return hash('sha256', $this->encode($value));
    }
}
