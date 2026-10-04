<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Search\Internal\SearchOptions;

it('passes on an empty environment with the shipped defaults and no secret', function (): void {
    $options = SearchOptions::fromArray([]);

    expect($options->driver)->toBe('null')
        ->and($options->cursorSecret)->toBeNull()
        ->and($options->textSearchConfig)->toBe('simple')
        ->and($options->scanBudget)->toBe(1000);
});

it('accumulates violations of the driver and the postgres section', function (): void {
    try {
        SearchOptions::fromArray(['driver' => '', 'postgres' => ['scan_budget' => 0]]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'driver: must be a non-empty string',
            'postgres.scan_budget: must be a positive integer',
        ]);
    }
});

it('wraps the cursor secret so it is never printed', function (): void {
    $options = SearchOptions::fromArray(['cursor_secret' => 'a-private-secret-value-of-sufficient-length']);

    expect($options->cursorSecret?->reveal())->toBe('a-private-secret-value-of-sufficient-length')
        ->and((string) $options->cursorSecret)->toBe('[redacted]');
});
