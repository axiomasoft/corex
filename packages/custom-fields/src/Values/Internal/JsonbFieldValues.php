<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Schemas\Contracts\SchemaHeads;
use CoreX\CustomFields\Schemas\Data\LockedFieldMapping;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Types\Contracts\FieldConversions;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\CustomFields\Types\Data\FieldReferences;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\FieldValueBatch;
use CoreX\CustomFields\Values\Data\FieldValueSnapshot;
use CoreX\CustomFields\Values\Data\FieldWriteReceipt;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\CustomFields\Values\Data\RemoveField;
use CoreX\CustomFields\Values\Events\FieldValuesChanged;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Enums\FieldUse;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Messaging\Contracts\Outbox;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;
use stdClass;

#[Internal]
final readonly class JsonbFieldValues implements FieldValues
{
    public function __construct(
        private DatabaseManager $databases, private WorkScopeRunner $runner, private CurrentWorkScope $current,
        private SchemaHeads $heads, private DataScopeAuthorizer $access, private FieldTypes $types,
        private FieldConversions $conversions, private Outbox $outbox, private ClockInterface $clock,
    ) {}

    public function read(DataScope $scope, RecordRef $record, FieldSelection $selection): FieldValueSnapshot
    {
        $result = $this->readMany(scope: $scope, records: new RecordSelection([$record]), selection: $selection);

        return $result->items[0] ?? throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
    }

    public function readMany(DataScope $scope, RecordSelection $records, FieldSelection $selection): FieldValueBatch
    {
        return $this->transaction(scope: $scope, work: function (ConnectionInterface $db) use ($scope, $records, $selection): FieldValueBatch {
            $permitted = [];
            $rejections = [];
            $mappings = [];
            $visibleFields = [];
            foreach ($records->records as $i => $record) {
                if (! $this->access->authorize(scope: $scope, record: $record, fields: new FieldSelection([]), use: FieldUse::Read)->allowed) {
                    $rejections[] = ['position' => $i, 'reason' => 'unavailable'];

                    continue;
                }
                $mapping = $mappings[$record->entity] ??= $this->heads->lockForWrite(db: $db, scope: $scope, entity: new EntityHandle($record->entity));
                $visibleFields[$i] = [];
                foreach ($selection->fields as $id) {
                    $fieldSelection = new FieldSelection([$id]);

                    if ($this->supports(mapping: $mapping, selection: $fieldSelection, use: FieldUse::Read) && $this->access->authorize(scope: $scope, record: $record, fields: $fieldSelection, use: FieldUse::Read)->allowed) {
                        $visibleFields[$i][] = $id;
                    }
                }
                $permitted[$i] = $record;
            }

            if ($permitted === []) {
                return new FieldValueBatch(items: [], rejections: $rejections);
            }
            // One SQL snapshot covers every document and all selected slots for the batch.
            $rows = $db->table('sys_attribute_documents')->where($this->logical($scope))->where(function (Builder $q) use ($permitted): void {
                foreach ($permitted as $record) {
                    $q->orWhere(['entity' => $record->entity, 'record_id' => $record->id]);
                }
            })->select(['entity', 'record_id', 'document_revision'])
                ->selectRaw('(SELECT jsonb_object_agg(k, v::text) FROM jsonb_each(slots) AS t(k,v)) AS encoded_slots')->get();
            $byRecord = [];
            foreach ($rows as $row) {
                $byRecord[$this->storedText($row->entity)][$this->storedText($row->record_id)] = $row;
            }
            $items = [];
            foreach ($permitted as $i => $record) {
                $row = $byRecord[$record->entity][$record->id] ?? null;
                $mapping = $mappings[$record->entity];
                $encoded = json_decode($this->storedText($row->encoded_slots ?? '{}'), true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($encoded)) {
                    throw new RuntimeException('Invalid stored slots.');
                }
                $values = [];
                foreach ($visibleFields[$i] as $id) {
                    $slot = $mapping->slots[$id];

                    if (isset($encoded[$slot])) {
                        $field = $mapping->schema->fields[$id];
                        $value = $this->types->resolve(type: $field->type, version: $field->codecVersion)->definition()->codec->decode(new JsonDocument($this->storedText($encoded[$slot])));

                        if (FieldReferences::visible(access: $this->access, scope: $scope, field: $field, value: $value) && $this->access->authorize(scope: $scope, record: $record, fields: new FieldSelection([$id]), use: FieldUse::Read)->allowed) {
                            $values[$id] = $value;
                        }
                    }
                }

                if (! $this->access->authorize(scope: $scope, record: $record, fields: new FieldSelection([]), use: FieldUse::Read)->allowed) {
                    $rejections[] = ['position' => $i, 'reason' => 'unavailable'];

                    continue;
                }
                $items[] = new FieldValueSnapshot(record: $record, schemaRevision: $mapping->schema->revision, valueRevision: new DocumentRevision($this->storedText($row->document_revision ?? 'absent')), values: $values);
            }

            return new FieldValueBatch(items: $items, rejections: $rejections);
        });
    }

    public function update(DataScope $scope, RecordRef $record, FieldPatch $patch, WriteToken $token): FieldWriteReceipt
    {
        return $this->transaction(scope: $scope, work: function (ConnectionInterface $db) use ($scope, $record, $patch, $token): FieldWriteReceipt {
            ReferenceWrites::enlist(connection: $db, scope: $scope, producer: 'axiomasoft/corex-custom-fields', kind: 'field-write', witness: ['entity' => $record->entity, 'patch' => $patch]);
            $selection = new FieldSelection(array_map(static fn ($op): string => $op->field->value, $patch->operations));

            if (! $this->permits(scope: $scope, record: $record, selection: $selection, use: FieldUse::Write)) {
                throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
            }
            $mapping = $this->heads->lockForWrite(db: $db, scope: $scope, entity: new EntityHandle($record->entity));

            if (! $this->supports(mapping: $mapping, selection: $selection, use: FieldUse::Write)) {
                throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
            }
            $digest = $patch->token(scope: $scope, record: $record, operationKey: $token->operationKey)->payloadHash;

            if (! hash_equals($digest, $token->payloadHash) || $token->expectedRecordVersion !== $patch->expectedDocument->value) {
                throw new FieldFailure(CustomFieldsErrorCode::IdempotencyConflict);
            }
            $key = [...$this->logical($scope), 'entity' => $record->entity, 'record_id' => $record->id];
            $receiptKey = [...$key, 'actor_kind' => $scope->principalKind, 'actor_id' => $scope->principalId, 'operation_key' => $token->operationKey];
            $existing = $db->table('sys_attribute_write_tokens')->where($receiptKey)->first();

            if ($existing !== null) {
                $this->authorizeReplayedReferences(scope: $scope, mapping: $mapping, patch: $patch);

                return $this->replay(row: $existing, digest: $digest, record: $record, token: $token);
            }
            $revision = new DocumentRevision(hash('sha256', random_bytes(32)));
            $documents = $db->table('sys_attribute_documents');
            $inserted = $documents->insertOrIgnoreReturning([...$key, 'slots' => '{}', 'document_revision' => $revision->value],
                ['document_revision'], array_keys($key));
            $created = $inserted->isNotEmpty();
            $actual = $this->storedText($db->table('sys_attribute_documents')->where($key)->lockForUpdate()->value('document_revision'));
            $receipt = $db->table('sys_attribute_write_tokens')->where($receiptKey)->first();

            if ($receipt !== null) {
                if ($created) {
                    $db->table('sys_attribute_documents')->where($key)->where('document_revision', $revision->value)->delete();
                }

                $this->authorizeReplayedReferences(scope: $scope, mapping: $mapping, patch: $patch);

                return $this->replay(row: $receipt, digest: $digest, record: $record, token: $token);
            }

            if ($patch->expectedSchema->value !== $mapping->schema->revision->value) {
                throw new FieldFailure(CustomFieldsErrorCode::SchemaRevisionMismatch);
            }

            if ($patch->expectedDocument->value !== ($created ? 'absent' : $actual)) {
                throw new FieldFailure(CustomFieldsErrorCode::DocumentRevisionMismatch);
            }
            $sets = [];
            $removes = [];
            foreach ($patch->operations as $position => $op) {
                $id = $op->field->value;
                $field = $mapping->schema->fields[$id];
                $slot = $mapping->slots[$id];
                $shadow = $mapping->shadowSlots[$id] ?? null;

                if ($op instanceof RemoveField) {
                    $removes[] = $slot;

                    if ($shadow !== null) {
                        $removes[] = $shadow;
                    }

                    continue;
                }
                $type = $this->types->resolve(type: $field->type, version: $field->codecVersion);

                try {
                    $value = $type->normalize(input: $op->input, field: $field);
                } catch (InvalidArgumentException $failure) {
                    throw new FieldFailure(reason: CustomFieldsErrorCode::InvalidFieldValue, path: '/operations/'.$position.'/input', previous: $failure);
                }

                if (! FieldReferences::visible(access: $this->access, scope: $scope, field: $field, value: $value)) {
                    throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
                }

                if ($value->document->canonicalJson === 'null' && (! $field->nullable || ! $type->definition()->nullable)) {
                    throw new InvalidArgumentException('Field requires a value.');
                }
                $encoded = $type->definition()->codec->encode($value);
                $sets[$slot] = $encoded->canonicalJson;

                if ($shadow !== null) {
                    $sets[$shadow] = $this->conversions->convert(document: $encoded, from: $field, to: $mapping->shadows[$id], converterId: $mapping->converterId, converterVersion: $mapping->converterVersion)->canonicalJson;
                }
            }
            $this->assertCanWrite(scope: $scope, record: $record, selection: $selection);
            // Build JSON from codec-produced documents without converting exact numbers to PHP floats.
            $entries = [];
            foreach ($sets as $slot => $json) {
                $entries[] = json_encode($slot, JSON_THROW_ON_ERROR).':'.$json;
            }
            $affected = $db->update('UPDATE sys_attribute_documents SET slots = (slots - ARRAY(SELECT jsonb_array_elements_text(?::jsonb))) || ?::jsonb, document_revision = ? WHERE account_id = ? AND account_incarnation = ? AND workspace_scope = ? AND entity = ? AND record_id = ? AND document_revision = ?',
                [json_encode($removes, JSON_THROW_ON_ERROR), '{'.implode(',', $entries).'}', $revision->value, ...array_values($key), $actual]);

            if ($affected !== 1) {
                throw new FieldFailure(CustomFieldsErrorCode::DocumentRevisionMismatch);
            }
            $messageId = (string) Str::uuid7();
            $receiptId = (string) Str::uuid7();
            $db->table('sys_attribute_write_tokens')->insert([...$receiptKey, 'request_digest' => $digest, 'schema_revision' => $mapping->schema->revision->value,
                'document_revision' => $revision->value, 'message_id' => $messageId, 'receipt_id' => $receiptId]);
            $event = new FieldValuesChanged(scope: $scope, subject: $record, fieldIds: $selection->fields, schemaRevision: $mapping->schema->revision->value, valueRevision: $revision->value);
            $this->outbox->append(connection: $db, message: $event->envelope(messageId: $messageId, occurredAt: $this->clock->now(), correlationId: $receiptId, causationId: $token->operationKey));

            return new FieldWriteReceipt(record: $record, schemaRevision: $mapping->schema->revision, valueRevision: $revision, messageId: $messageId, operationKey: $token->operationKey, receiptId: $receiptId);
        });
    }

    private function storedText(mixed $value): string
    {
        return is_string($value) ? $value : throw new RuntimeException('Invalid persisted field data.');
    }

    private function replay(stdClass $row, string $digest, RecordRef $record, WriteToken $token): FieldWriteReceipt
    {
        if (! hash_equals($this->storedText($row->request_digest), $digest)) {
            throw new FieldFailure(CustomFieldsErrorCode::IdempotencyConflict);
        }

        return new FieldWriteReceipt(record: $record, schemaRevision: new SchemaRevision($this->storedText($row->schema_revision)), valueRevision: new DocumentRevision($this->storedText($row->document_revision)),
            messageId: $this->storedText($row->message_id), operationKey: $token->operationKey, receiptId: $this->storedText($row->receipt_id));
    }

    /**
     * @template T
     *
     * @param  Closure(ConnectionInterface):T  $work
     * @return T
     */
    private function transaction(DataScope $scope, Closure $work): mixed
    {
        return $this->runner->run(scope: $scope, work: function () use ($scope, $work): mixed {
            $db = $this->databases->connection();
            $this->current->assert(scope: $scope, connection: $db);

            if ($db->getDriverName() !== 'pgsql') {
                throw new RuntimeException('Custom field storage requires PostgreSQL.');
            }

            return $db->transaction(function () use ($scope, $db, $work): mixed {
                $this->current->assert(scope: $scope, connection: $db);

                return $work($db);
            });
        });
    }

    private function assertCanWrite(DataScope $scope, RecordRef $record, FieldSelection $selection): void
    {
        if (! $this->permits(scope: $scope, record: $record, selection: $selection, use: FieldUse::Write)) {
            throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
        }
    }

    private function permits(DataScope $scope, RecordRef $record, FieldSelection $selection, FieldUse $use): bool
    {
        $recordSelection = new FieldSelection([]);

        if (! $this->access->authorize(scope: $scope, record: $record, fields: $recordSelection, use: FieldUse::Read)->allowed
            || ($use !== FieldUse::Read && ! $this->access->authorize(scope: $scope, record: $record, fields: $recordSelection, use: $use)->allowed)) {
            return false;
        }
        foreach ($selection->fields as $id) {
            if (! $this->access->authorize(scope: $scope, record: $record, fields: new FieldSelection([$id]), use: $use)->allowed) {
                return false;
            }
        }

        return true;
    }

    private function supports(LockedFieldMapping $mapping, FieldSelection $selection, FieldUse $use): bool
    {
        foreach ($selection->fields as $id) {
            $field = $mapping->schema->fields[$id] ?? null;

            if ($field === null || ! isset($mapping->slots[$id]) || $field->lifecycle !== 'active' || ! $field->permits($use, $this->types->resolve(type: $field->type, version: $field->codecVersion)->definition())) {
                return false;
            }
        }

        return true;
    }

    private function authorizeReplayedReferences(DataScope $scope, LockedFieldMapping $mapping, FieldPatch $patch): void
    {
        foreach ($patch->operations as $op) {
            $field = $mapping->schema->fields[$op->field->value];

            if (! $op instanceof RemoveField && ! FieldReferences::visible(access: $this->access, scope: $scope, field: $field, value: new FieldValue($op->input->document))) {
                throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
            }
        }
    }

    /** @return array{account_id:string,account_incarnation:string,workspace_scope:string} */
    private function logical(DataScope $scope): array
    {
        return ['account_id' => $scope->accountId, 'account_incarnation' => $scope->accountIncarnation,
            'workspace_scope' => $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId];
    }
}
