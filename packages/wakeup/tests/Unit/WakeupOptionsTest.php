<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Config\Laravel\CoreOptionsParser;
use CoreX\Wakeup\Internal\DangerousModesCheck;
use CoreX\Wakeup\Internal\WakeupOptions;
use CoreX\Wakeup\Tests\Support\OptionsProbeHandler;

it('passes on an empty environment with the shipped defaults', function (): void {
    $options = WakeupOptions::fromArray([]);

    expect($options->leaseSeconds)->toBe(60)
        ->and($options->maxAttempts)->toBe(5)
        ->and($options->handlers)->toBe([])
        ->and($options->dangerousModes())->toBe([]);
});

it('accumulates violations of both limits', function (): void {
    try {
        WakeupOptions::fromArray(['lease_seconds' => 0, 'max_attempts' => 'x']);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'lease_seconds: must be a positive integer',
            'max_attempts: must be a positive integer',
        ]);
    }
});

it('rejects the scalar handler form', function (): void {
    expect(fn () => WakeupOptions::fromArray(['handlers' => ['a' => 'App\\Handler']]))
        ->toThrow(InvalidConfiguration::class, 'handlers.a: requires handler, version');
});

it('lists a legacy-unstamped handler as a dangerous mode that production must acknowledge by exact path', function (): void {
    $options = WakeupOptions::fromArray(['handlers' => [
        'test.reminder' => ['handler' => OptionsProbeHandler::class, 'version' => 'test/1', 'accept_legacy_unstamped' => true],
    ]]);
    $path = 'corex-wakeup.handlers.test.reminder.accept_legacy_unstamped';

    expect($options->dangerousModes())->toBe([$path])
        ->and((new DangerousModesCheck($options, CoreOptionsParser::fromArray([])))->violations(true))
        ->toBe([$path.': dangerous mode is not acknowledged for production'])
        ->and((new DangerousModesCheck($options, CoreOptionsParser::fromArray(['acknowledged_dangerous_modes' => [$path]])))->violations(true))
        ->toBe([]);
});
