<?php

declare(strict_types=1);

use CoreX\Notifications\Internal\NotificationsOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('passes on an empty environment with the shipped defaults', function (): void {
    $options = NotificationsOptions::fromArray([]);

    expect($options->digestEnabled)->toBeFalse()
        ->and($options->deliveryClaimSeconds)->toBe(60)
        ->and($options->deliveryMaxAttempts)->toBe(5);
});

it('accumulates violations across the digest and delivery sections', function (): void {
    try {
        NotificationsOptions::fromArray(['digest' => ['enabled' => 'on'], 'delivery' => ['claim_seconds' => 0]]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'digest.enabled: must be a boolean',
            'delivery.claim_seconds: must be a positive integer',
        ]);
    }
});
