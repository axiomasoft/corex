<?php

declare(strict_types=1);

use CoreX\CustomFields\Data\AttributeWriteResult;
use CoreX\CustomFields\Data\FilterNode;
use CoreX\CustomFields\Data\OperationRef;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\SchemaReceipt;
use CoreX\CustomFields\Data\ValueBatch;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;

it('rejects malformed immutable boundary values', function (): void {
    $hash = str_repeat('a', 64);

    expect((new FieldSelection([]))->fields)->toBe([])
        ->and(fn (): RecordSelection => new RecordSelection([]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): FilterNode => new FilterNode([]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): ValueBatch => new ValueBatch([new RecordRef('contact', 'record-1')]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): AttributeWriteResult => new AttributeWriteResult('invalid', 'version-1', $hash))->toThrow(InvalidArgumentException::class)
        ->and(fn (): SchemaReceipt => new SchemaReceipt(new OperationRef('operation-1'), 'verified', $hash, 'error'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): SchemaReceipt => new SchemaReceipt(new OperationRef('operation-1'), 'failed', $hash, null))->toThrow(InvalidArgumentException::class);
});
