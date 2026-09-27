<?php

declare(strict_types=1);

namespace CoreX\Attributes\Tests\Support;

use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Support\AttributeWriteResult;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValueBag;
use CoreX\Attributes\Support\ValueBatch;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;

final class RecordingAttributeStore implements AttributeStore
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
