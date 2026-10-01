<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\CompiledModule;
use CoreX\Modules\Data\CompiledRegistry;
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
        module: 'axiomasoft/corex-workflow',
        entityHandle: 'test.ticket',
        eventId: 'event:1',
        eventName: 'created',
        subjectRef: 'ticket:1',
        definitionRef: 'definition:1',
        versionRef: 'version:1',
        scalarSnapshot: JsonDocuments::capture($snapshot),
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

    expect($binding->signal(new SignalRequest($context, 'approved', 'ticket:1', JsonDocuments::capture([]), 'workflow:signal:1', new DateTimeImmutable('+1 hour')))->status)->toBe('buffered')
        ->and($binding->fire(new TransitionRequest($context, 'ticket:1', 'approve', 'user:1', JsonDocuments::capture([]), 'workflow:transition:1'))->status)->toBe('completed');
});

it('does not send expired signals to the engine adapter', function (): void {
    $binding = app(WorkflowBinding::class);
    $context = app(TenantContextResolver::class)->current();

    expect($binding->signal(new SignalRequest($context, 'approved', 'ticket:1', JsonDocuments::capture([]), 'workflow:signal:expired', new DateTimeImmutable('-1 second')))->status)->toBe('expired');
});

it('rejects empty definition and version refs before insert', function (string $definitionRef, string $versionRef): void {
    $binding = app(WorkflowBinding::class);
    $context = app(TenantContextResolver::class)->current();

    $binding->capture(new TriggerRequest(
        context: $context,
        module: 'axiomasoft/corex-workflow',
        entityHandle: 'test.ticket',
        eventId: 'event:1',
        eventName: 'created',
        subjectRef: 'ticket:1',
        definitionRef: $definitionRef,
        versionRef: $versionRef,
        scalarSnapshot: JsonDocuments::capture(['status' => 'new']),
        requestKey: 'workflow:empty:1',
    ));
})->with([
    'empty definition' => ['', 'version:1'],
    'blank version' => ['definition:1', ' '],
])->throws(InvalidArgumentException::class);

it('stores the stamp inside the payload and excludes the fingerprint from the hash', function (): void {
    $binding = app(WorkflowBinding::class);

    expect($binding->capture(triggerRequest('workflow:stamp:hash'))->status)->toBe('created');

    $row = $this->app['db']->table('sys_workflow_intents')->where('request_key', 'workflow:stamp:hash')->first();
    $stored = json_decode((string) $row->payload, true);

    expect($stored['identity'])->toMatchArray([
        'schema' => 'workflow-definition/1',
        'point' => 'workflow.trigger',
        'id' => 'axiomasoft/corex-workflow:test.ticket:created',
        'version' => 'version:1',
    ])->and($stored['registryFingerprint'])->toBe('module-free')
        ->and($row->payload_hash)->toBe(hash(
            'sha256',
            "workflow-definition/1\0trigger\0workflow:stamp:hash\0".json_encode(
                array_diff_key($stored, ['registryFingerprint' => true]),
                JSON_THROW_ON_ERROR,
            ),
        ))
        ->and($binding->capture(triggerRequest('workflow:stamp:hash'))->status)->toBe('existing');
});

it('conflicts when the pinned version does not match the stored identity', function (): void {
    $binding = app(WorkflowBinding::class);
    $binding->capture(triggerRequest('workflow:stamp:version'));
    $changed = triggerRequest('workflow:stamp:version');
    $changed = new TriggerRequest(
        context: $changed->context,
        module: $changed->module,
        entityHandle: $changed->entityHandle,
        eventId: $changed->eventId,
        eventName: $changed->eventName,
        subjectRef: $changed->subjectRef,
        definitionRef: $changed->definitionRef,
        versionRef: 'version:2',
        scalarSnapshot: JsonDocuments::capture(JsonDocuments::map($changed->scalarSnapshot)),
        requestKey: $changed->requestKey,
    );

    expect($binding->capture($changed)->status)->toBe('conflict');
});

it('admits a legacy non-empty ref with the original hash and does not rewrite it', function (): void {
    $context = app(TenantContextResolver::class)->current();
    $business = [
        'event_id' => 'event:1',
        'event_name' => 'created',
        'entity_handle' => 'test.ticket',
        'scalar_snapshot' => ['status' => 'new'],
    ];
    ksort($business['scalar_snapshot']);
    $encoded = json_encode($business, JSON_THROW_ON_ERROR);
    $hash = hash('sha256', "engine-binding/1\0trigger\0workflow:legacy:1\0{$encoded}");
    $this->app['db']->table('sys_workflow_intents')->insert([
        'id' => '01999999-9999-7999-8999-999999999999',
        'request_key' => 'workflow:legacy:1',
        'payload_hash' => $hash,
        'context_hash' => hash('sha256', $context->account->id."\0"),
        'restore_epoch' => str_repeat('0', 64),
        'module' => 'axiomasoft/corex-workflow',
        'kind' => 'trigger',
        'definition_ref' => 'definition:1',
        'version_ref' => 'version:1',
        'subject_ref' => 'ticket:1',
        'payload' => $encoded,
        'state' => 'pending',
        'claim_epoch' => 0,
        'attempt' => 0,
        'generation' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $binding = app(WorkflowBinding::class);

    expect($binding->capture(triggerRequest('workflow:legacy:1'))->status)->toBe('existing')
        ->and($this->app['db']->table('sys_workflow_intents')->where('request_key', 'workflow:legacy:1')->value('payload'))->toBe($encoded)
        ->and($binding->capture(triggerRequest('workflow:legacy:1', ['status' => 'changed']))->status)->toBe('conflict');
});

it('treats an unrelated registry fingerprint change as the same work', function (): void {
    $registry = new class implements ModuleRegistry
    {
        public string $fingerprint = 'fingerprint-a';

        public function all(): array
        {
            return [];
        }

        public function get(string $name): CompiledModule
        {
            throw new RuntimeException('unused');
        }

        public function active(TenantContext $ctx): array
        {
            return [];
        }

        public function isActive(string $name, TenantContext $ctx): bool
        {
            return false;
        }

        public function compiled(): CompiledRegistry
        {
            return new CompiledRegistry(
                entities: [],
                entityFields: [],
                permissions: [],
                api: [],
                filament: [],
                menu: [],
                routes: [],
                workflow: [],
                fingerprint: $this->fingerprint,
            );
        }
    };
    $this->app->instance(ModuleRegistry::class, $registry);
    $binding = app(WorkflowBinding::class);

    expect($binding->capture(triggerRequest('workflow:stamp:drift'))->status)->toBe('created');

    $registry->fingerprint = 'fingerprint-b';

    expect($binding->capture(triggerRequest('workflow:stamp:drift'))->status)->toBe('existing')
        ->and(json_decode((string) $this->app['db']->table('sys_workflow_intents')->where('request_key', 'workflow:stamp:drift')->value('payload'), true)['registryFingerprint'])->toBe('fingerprint-a');
});
