<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Config\Laravel\CoreOptionsParser;
use CoreX\Workflow\Internal\DangerousModesCheck;
use CoreX\Workflow\Internal\WorkflowOptions;

it('passes on an empty environment with the shipped default', function (): void {
    expect(WorkflowOptions::fromArray([])->enforceDeclarations)->toBeTrue()
        ->and(WorkflowOptions::fromArray([])->dangerousModes())->toBe([]);
});

it('rejects a non-boolean enforce_declarations flag', function (): void {
    try {
        WorkflowOptions::fromArray(['enforce_declarations' => 'no']);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe(['enforce_declarations: must be a boolean']);
    }
});

it('lists a disabled declaration guard as a dangerous mode that production must acknowledge', function (): void {
    $options = WorkflowOptions::fromArray(['enforce_declarations' => false]);
    $path = 'corex-workflow.enforce_declarations';
    $unacknowledged = new DangerousModesCheck($options, CoreOptionsParser::fromArray([]));
    $acknowledged = new DangerousModesCheck($options, CoreOptionsParser::fromArray(['acknowledged_dangerous_modes' => [$path]]));

    expect($options->dangerousModes())->toBe([$path])
        ->and($unacknowledged->violations(true))->toBe([$path.': dangerous mode is not acknowledged for production'])
        ->and($unacknowledged->violations(false))->toBe([])
        ->and($acknowledged->violations(true))->toBe([]);
});
