<?php

declare(strict_types=1);

use CoreX\SearchMeilisearch\Internal\MeilisearchOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('passes on an empty environment because presence is a runtime requirement of the driver', function (): void {
    $options = MeilisearchOptions::fromArray([]);

    expect($options->host)->toBeNull()
        ->and($options->indexUid)->toBeNull()
        ->and($options->key)->toBeNull();
});

it('accumulates type violations without revealing the key', function (): void {
    try {
        MeilisearchOptions::fromArray(['host' => 7, 'index_uid' => [], 'key' => 12345]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'host: must be null or a string',
            'index_uid: must be null or a string',
            'key: must be null or a string',
        ]);
    }
});

it('keeps the key behind a redacted reference', function (): void {
    $options = MeilisearchOptions::fromArray(['key' => 'local-test-key']);

    expect($options->key?->reveal())->toBe('local-test-key')
        ->and((string) $options->key)->toBe('[redacted]');
});
