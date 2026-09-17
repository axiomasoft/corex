<?php

declare(strict_types=1);

use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\FilterNode;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\Schema\OperationRef;
use CoreX\Attributes\Support\Schema\SchemaReceipt;
use CoreX\Attributes\Support\ValueBatch;
use CoreX\Attributes\Support\WriteResult;
use CoreX\Support\Data\RecordRef;

it('rejects malformed immutable boundary values', function (): void {
    $hash = str_repeat('a', 64);

    expect(fn (): FieldSelection => new FieldSelection([]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): RecordSelection => new RecordSelection([]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): FilterNode => new FilterNode([]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): ValueBatch => new ValueBatch([new RecordRef('contact', 'record-1')]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): WriteResult => new WriteResult('invalid', 'version-1', $hash))->toThrow(InvalidArgumentException::class)
        ->and(fn (): SchemaReceipt => new SchemaReceipt(new OperationRef('operation-1'), 'verified', $hash, 'error'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): SchemaReceipt => new SchemaReceipt(new OperationRef('operation-1'), 'failed', $hash, null))->toThrow(InvalidArgumentException::class);
});
