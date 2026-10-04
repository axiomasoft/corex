<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Panels\Internal\PanelsOptions;

it('passes on an empty environment with the shipped provider tag', function (): void {
    expect(PanelsOptions::fromArray([])->providerTag)->toBe('corex.panels.providers');
});

it('accumulates shape violations without revealing values', function (): void {
    try {
        PanelsOptions::fromArray(['provider_tag' => '']);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe(['provider_tag: must be a non-empty string']);
    }
});

it('keeps a custom provider tag', function (): void {
    expect(PanelsOptions::fromArray(['provider_tag' => 'app.panels'])->providerTag)->toBe('app.panels');
});
