<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Transfer\Internal\TransferOptions;

it('passes on an empty environment with the shipped limits', function (): void {
    $options = TransferOptions::fromArray([]);

    expect($options->limitsMaxRecordBytes)->toBe(1_048_576)
        ->and($options->limitsMaxFields)->toBe(256)
        ->and($options->limitsBatchRows)->toBe(500);
});

it('accumulates a violation for every non-positive limit', function (): void {
    try {
        TransferOptions::fromArray(['limits' => ['max_record_bytes' => 0, 'max_fields' => -1, 'batch_rows' => 'x']]);
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
    expect(TransferOptions::fromArray(['limits' => ['batch_rows' => 50]])->limitsBatchRows)->toBe(50);
});
