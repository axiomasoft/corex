<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Data;

use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Types\Data\FieldJson;
use CoreX\CustomFields\Types\Data\FieldList;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldPatch
{
    /** @param non-empty-list<SetField|RemoveField> $operations */
    public function __construct(public array $operations, public SchemaRevision $expectedSchema, public DocumentRevision $expectedDocument)
    {
        FieldList::check(values: $operations, classes: [SetField::class, RemoveField::class], min: 1);
        $seen = [];
        foreach ($operations as $op) {
            if (isset($seen[$op->field->value])) {
                throw new InvalidArgumentException('Field operations must address distinct fields.');
            }
            $seen[$op->field->value] = true;
        }
    }

    public function token(DataScope $scope, RecordRef $record, string $operationKey): WriteToken
    {
        $ops = [];
        foreach ($this->operations as $op) {
            $ops[] = $op instanceof SetField
                ? ['set', $op->field->value, FieldJson::canonical($op->input->document->canonicalJson)]
                : ['remove', $op->field->value];
        }
        $digest = hash('sha256', FieldJson::encode(['corex.fields.write.v2', DataScope::VERSION, get_object_vars($scope),
            $record->entity, $record->id, $this->expectedSchema->value, $this->expectedDocument->value, $ops]));

        return new WriteToken(operationKey: $operationKey, payloadHash: $digest, expectedRecordVersion: $this->expectedDocument->value);
    }
}
