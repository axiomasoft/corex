<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Support;

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Data\AttributeWriteResult;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\ValueBag;
use CoreX\CustomFields\Data\ValueBatch;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;

final readonly class RecordingAttributeStore implements AttributeStore
{
    public function __construct(private string $engine) {}

    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
    {
        return new ValueBag(values: ['engine' => $this->engine, 'id' => $record->id]);
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): AttributeWriteResult
    {
        return new AttributeWriteResult(status: 'applied', recordVersion: '1', payloadHash: str_repeat('a', 64));
    }

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch
    {
        return new ValueBatch(records: array_map(
            fn (RecordRef $record): ValueBag => new ValueBag(values: ['engine' => $this->engine, 'id' => $record->id]),
            $records->records,
        ));
    }
}
