<?php

declare(strict_types=1);

use CoreX\RecurringTasks\Internal\RecurringTasksOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('passes on an empty environment with the shipped defaults', function (): void {
    $options = RecurringTasksOptions::fromArray([]);

    expect($options->wakeupEnabled)->toBeFalse()
        ->and($options->wakeupHandlerKind)->toBe('task-recurrence.materialize')
        ->and($options->wakeupHandlerVersion)->toBe('task-materialization/1');
});

it('accumulates violations in the wakeup section', function (): void {
    try {
        RecurringTasksOptions::fromArray(['wakeup' => ['enabled' => 1, 'handler_kind' => '', 'handler_version' => '']]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'wakeup.enabled: must be a boolean',
            'wakeup.handler_kind: must be a non-empty string',
            'wakeup.handler_version: must be a non-empty string',
        ]);
    }
});
