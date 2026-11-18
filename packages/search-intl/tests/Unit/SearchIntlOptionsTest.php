<?php

declare(strict_types=1);

use CoreX\SearchIntl\Internal\SearchIntlOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('is disabled on an empty environment', function (): void {
    expect(SearchIntlOptions::fromArray([])->enabled)->toBeFalse();
});

it('rejects a non-boolean enabled flag', function (): void {
    try {
        SearchIntlOptions::fromArray(['enabled' => 'yes']);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe(['enabled: must be a boolean']);
    }
});

it('enables the bridge only for an explicit true', function (): void {
    expect(SearchIntlOptions::fromArray(['enabled' => true])->enabled)->toBeTrue();
});
