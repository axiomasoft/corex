<?php

declare(strict_types=1);

use CoreX\ImportExport\Internal\ImportExportOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('passes on an empty environment with the shipped limits', function (): void {
    $options = ImportExportOptions::fromArray([]);

    expect($options->limitsMaxRecordBytes)->toBe(1_048_576)
        ->and($options->limitsMaxFields)->toBe(256)
        ->and($options->limitsBatchRows)->toBe(500)
        ->and($options->limitsMaxMemoryMiB)->toBe(128);
});

it('accumulates a violation for every non-positive limit', function (): void {
    try {
        ImportExportOptions::fromArray(['limits' => ['max_record_bytes' => 0, 'max_fields' => -1, 'batch_rows' => 'x']]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'limits.max_record_bytes: must be a positive integer',
            'limits.max_fields: must be a positive integer',
            'limits.batch_rows: must be a positive integer',
        ]);
    }
});

it('reads an overridden limit', function (): void {
    expect(ImportExportOptions::fromArray(['limits' => ['batch_rows' => 50]])->limitsBatchRows)->toBe(50);
});

it('accepts a host process memory budget within the supported ceiling', function (): void {
    expect(ImportExportOptions::fromArray(['limits' => ['max_memory_mib' => 512]])->limitsMaxMemoryMiB)->toBe(512);
});

it('rejects invalid host memory budgets during configuration', function (mixed $budget): void {
    expect(fn () => ImportExportOptions::fromArray(['limits' => ['max_memory_mib' => $budget]]))->toThrow(InvalidConfiguration::class);
})->with([0, -1, 1025, '512']);
