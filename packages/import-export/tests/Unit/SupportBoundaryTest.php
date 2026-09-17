<?php

declare(strict_types=1);

use CoreX\ImportExport\Support\CanonicalRecord;
use CoreX\ImportExport\Support\ConflictRule;
use CoreX\ImportExport\Support\MappingResult;
use CoreX\ImportExport\Support\SourceCursor;
use CoreX\ImportExport\Support\SourceManifest;
use CoreX\ImportExport\Support\TransferLimits;

it('accepts only a bounded immutable source manifest and canonical cursor', function (): void {
    $hash = str_repeat('a', 64);
    $manifest = new SourceManifest('object://imports/1', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash);
    $cursor = new SourceCursor('0', '0', $hash);

    expect($manifest->sha256)->toBe($hash)->and($cursor->ordinal)->toBe('0');
    expect(fn (): SourceCursor => new SourceCursor('01', null, $hash))->toThrow(InvalidArgumentException::class);
    expect(fn (): TransferLimits => new TransferLimits(batchRows: 501))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid mapping and target payload shapes before a target is called', function (): void {
    $hash = str_repeat('b', 64);
    $record = new CanonicalRecord('contact', 'archive', 'doc-1', 'rev-1', null, ['name' => 'Ada'], $hash);

    expect((new MappingResult('mapped', $record, null))->record)->toBe($record)
        ->and(fn (): MappingResult => new MappingResult('mapped', null, 'invalid'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): ConflictRule => new ConflictRule('last-write-wins'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): CanonicalRecord => new CanonicalRecord('contact', 'archive', 'doc-1', 'rev-1', null, ['money' => 1.5], $hash))
        ->toThrow(InvalidArgumentException::class);
});
