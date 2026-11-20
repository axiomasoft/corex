<?php

declare(strict_types=1);

use CoreX\SearchMeilisearch\Internal\MeilisearchOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('passes on an empty environment before a driver is selected', function (): void {
    expect(MeilisearchOptions::fromArray([])->host)->toBeNull();
});

it('reports an invalid endpoint type', function (): void {
    try {
        MeilisearchOptions::fromArray(['host' => 7]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe(['host: must be null or a string']);
    }
});

it('stores only the endpoint in the adapter options', function (): void {
    $options = MeilisearchOptions::fromArray(['host' => 'http://127.0.0.1:7700']);

    expect(get_object_vars($options))->toBe(['host' => 'http://127.0.0.1:7700']);
});
