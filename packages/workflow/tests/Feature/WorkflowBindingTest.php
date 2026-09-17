<?php

declare(strict_types=1);

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Tests\TestCase;

uses(TestCase::class);

function triggerRequest(string $key, array $snapshot = ['status' => 'new']): TriggerRequest
{
    $context = app(TenantContextResolver::class)->current();

    return new TriggerRequest(
        context: $context,
        module: 'corex/workflow',
        entityHandle: 'test.ticket',
        eventId: 'event:1',
        eventName: 'created',
        subjectRef: 'ticket:1',
        definitionRef: 'definition:1',
        versionRef: 'version:1',
        scalarSnapshot: $snapshot,
        requestKey: $key,
    );
}

it('captures an immutable intent and replays the same request key', function (): void {
    $binding = app(WorkflowBinding::class);

    expect($binding->capture(triggerRequest('workflow:test:1'))->status)->toBe('created')
        ->and($binding->capture(triggerRequest('workflow:test:1'))->status)->toBe('existing')
        ->and($this->app['db']->table('sys_workflow_intents')->where('request_key', 'workflow:test:1')->value('state'))->toBe('pending');
});

it('rejects a changed immutable payload for the same request key', function (): void {
    $binding = app(WorkflowBinding::class);

    $binding->capture(triggerRequest('workflow:test:conflict'));

    expect($binding->capture(triggerRequest('workflow:test:conflict', ['status' => 'changed']))->status)->toBe('conflict');
});

it('delegates signal and transition operations to the configured engine adapter', function (): void {
    $binding = app(WorkflowBinding::class);
    $context = app(TenantContextResolver::class)->current();

    expect($binding->signal(new SignalRequest($context, 'approved', 'ticket:1', [], 'workflow:signal:1', new DateTimeImmutable('+1 hour')))->status)->toBe('buffered')
        ->and($binding->fire(new TransitionRequest($context, 'ticket:1', 'approve', 'user:1', [], 'workflow:transition:1'))->status)->toBe('completed');
});

it('does not send expired signals to the engine adapter', function (): void {
    $binding = app(WorkflowBinding::class);
    $context = app(TenantContextResolver::class)->current();

    expect($binding->signal(new SignalRequest($context, 'approved', 'ticket:1', [], 'workflow:signal:expired', new DateTimeImmutable('-1 second')))->status)->toBe('expired');
});
