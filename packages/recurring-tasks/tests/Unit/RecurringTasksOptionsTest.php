<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Config\Laravel\CoreOptionsParser;
use CoreX\RecurringTasks\Internal\DangerousModesCheck;
use CoreX\RecurringTasks\Internal\RecurringTasksOptions;

it('passes on an empty environment with the shipped defaults', function (): void {
    $options = RecurringTasksOptions::fromArray([]);

    expect($options->wakeupEnabled)->toBeFalse()
        ->and($options->wakeupHandlerKind)->toBe('task-recurrence.materialize')
        ->and($options->wakeupHandlerVersion)->toBe('task-materialization/1')
        ->and($options->completionCaptureEnabled)->toBeFalse()
        ->and($options->completionCaptureDefinitionRef)->toBe('task-completion/1')
        ->and($options->dangerousModes())->toBe([]);
});

it('accumulates violations across the wakeup and capture sections', function (): void {
    try {
        RecurringTasksOptions::fromArray(['wakeup' => ['enabled' => 1, 'handler_kind' => ''], 'completion_capture' => ['version_ref' => '']]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'wakeup.enabled: must be a boolean',
            'wakeup.handler_kind: must be a non-empty string',
            'completion_capture.version_ref: must be a non-empty string',
        ]);
    }
});

it('lists legacy unstamped materialization wakeups as a dangerous mode that production must acknowledge', function (): void {
    $options = RecurringTasksOptions::fromArray(['wakeup' => ['accept_legacy_unstamped' => true]]);
    $path = 'corex-recurring-tasks.wakeup.accept_legacy_unstamped';

    expect($options->dangerousModes())->toBe([$path])
        ->and(new DangerousModesCheck($options, CoreOptionsParser::fromArray([]))->violations(true))
        ->toBe([$path.': dangerous mode is not acknowledged for production'])
        ->and(new DangerousModesCheck($options, CoreOptionsParser::fromArray(['acknowledged_dangerous_modes' => [$path]]))->violations(true))
        ->toBe([]);
});
