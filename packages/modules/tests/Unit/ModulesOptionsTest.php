<?php

declare(strict_types=1);

use CoreX\Modules\Support\ModulesOptions;
use CoreX\Support\InvalidConfiguration;

it('allows a null module connection', function (): void {
    $options = ModulesOptions::fromArray(['connection' => null]);

    expect($options->connection)->toBeNull()
        ->and($options->cacheTtl)->toBe(3600)
        ->and($options->cachePrefix)->toBe('corex-modules');
});

it('rejects a zero or negative cache ttl', function (int $ttl): void {
    try {
        ModulesOptions::fromArray(['cache' => ['ttl' => $ttl]]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toContain('cache.ttl: must be a positive integer');
    }
})->with([0, -1]);
