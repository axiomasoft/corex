<?php

declare(strict_types=1);

use CoreX\CustomFields\Support\AttributeWriteResult;
use CoreX\CustomFields\Support\FieldSelection;
use CoreX\CustomFields\Support\FilterNode;
use CoreX\CustomFields\Support\RecordSelection;
use CoreX\CustomFields\Support\Schema\OperationRef;
use CoreX\CustomFields\Support\Schema\SchemaReceipt;
use CoreX\CustomFields\Support\ValueBatch;
use CoreX\Data\RecordRef;

it('rejects malformed immutable boundary values', function (): void {
    $hash = str_repeat('a', 64);

    expect(fn (): FieldSelection => new FieldSelection([]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): RecordSelection => new RecordSelection([]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): FilterNode => new FilterNode([]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): ValueBatch => new ValueBatch([new RecordRef('contact', 'record-1')]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): AttributeWriteResult => new AttributeWriteResult('invalid', 'version-1', $hash))->toThrow(InvalidArgumentException::class)
        ->and(fn (): SchemaReceipt => new SchemaReceipt(new OperationRef('operation-1'), 'verified', $hash, 'error'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): SchemaReceipt => new SchemaReceipt(new OperationRef('operation-1'), 'failed', $hash, null))->toThrow(InvalidArgumentException::class);
});
