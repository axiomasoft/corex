<?php

declare(strict_types=1);

use CoreX\Config\Data\SecretRef;
use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Config\Laravel\CoreOptionsParser;

it('resolves settings cache ttl by key existence', function (array $config, ?int $expected): void {
    expect(CoreOptionsParser::fromArray($config)->settingsCacheTtl)->toBe($expected);
})->with([
    'missing settings' => [[], 3600],
    'missing key' => [['settings' => ['cache_store' => 'array']], 3600],
    'explicit null' => [['settings' => ['cache_ttl' => null]], null],
    'zero' => [['settings' => ['cache_ttl' => 0]], 0],
    'positive' => [['settings' => ['cache_ttl' => 120]], 120],
]);

it('accumulates invalid cache ttl and cache store in one exception', function (): void {
    try {
        CoreOptionsParser::fromArray([
            'settings' => [
                'cache_store' => '',
                'cache_ttl' => -1,
            ],
        ]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'settings.cache_store: must be a non-empty string',
            'settings.cache_ttl: must be null or a non-negative integer',
        ])->and($exception->getMessage())->toBe(
            'Invalid configuration (2): settings.cache_store: must be a non-empty string; settings.cache_ttl: must be null or a non-negative integer',
        );
    }
});

it('redacts a secret from string, dump, json, and serialization', function (): void {
    $plaintext = 'super-secret-value';
    $secret = new SecretRef($plaintext);

    expect("prefix {$secret}")->toBe('prefix [redacted]')
        ->and((string) $secret)->toBe('[redacted]')
        ->and($secret->reveal())->toBe($plaintext)
        ->and($secret->isPresent())->toBeTrue();

    ob_start();
    var_dump($secret);
    $dump = ob_get_clean();

    $encoded = json_encode($secret);

    expect($dump)->toBeString()
        ->and($dump)->not->toContain($plaintext)
        ->and($dump)->toContain('[redacted]')
        ->and($encoded)->toBeString()
        ->and($encoded)->not->toContain($plaintext)
        ->and(fn () => serialize($secret))->toThrow(LogicException::class);
});
