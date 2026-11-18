<?php

declare(strict_types=1);

use CoreX\Config\Laravel\CoreOptionsParser;
use CoreX\Notifications\Internal\DangerousModesCheck;
use CoreX\Notifications\Internal\NotificationsOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('passes on an empty environment with the shipped defaults', function (): void {
    $options = NotificationsOptions::fromArray([]);

    expect($options->digestEnabled)->toBeFalse()
        ->and($options->digestAcceptLegacyUnstamped)->toBeFalse()
        ->and($options->deliveryClaimSeconds)->toBe(60)
        ->and($options->deliveryMaxAttempts)->toBe(5)
        ->and($options->dangerousModes())->toBe([]);
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

it('lists legacy unstamped digest wakeups as a dangerous mode that production must acknowledge', function (): void {
    $options = NotificationsOptions::fromArray(['digest' => ['accept_legacy_unstamped' => true]]);
    $path = 'corex-notifications.digest.accept_legacy_unstamped';

    expect($options->dangerousModes())->toBe([$path])
        ->and(new DangerousModesCheck($options, CoreOptionsParser::fromArray([]))->violations(true))
        ->toBe([$path.': dangerous mode is not acknowledged for production'])
        ->and(new DangerousModesCheck($options, CoreOptionsParser::fromArray(['acknowledged_dangerous_modes' => [$path]]))->violations(true))
        ->toBe([])
        ->and(new DangerousModesCheck($options, CoreOptionsParser::fromArray([]))->violations(false))
        ->toBe([]);
});
