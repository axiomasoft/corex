<?php

declare(strict_types=1);

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\CaptureResult;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\CaptureStatus;
use CoreX\Workflow\Internal\WorkflowOptions;
use CoreX\Workflow\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Compiler\CompiledModule;
use Packman\Modules\Data\Compiler\CompiledRegistry;

uses(TestCase::class);

function triggerRequest(string $key, array $snapshot = ['status' => 'new']): TriggerRequest
{
    $context = resolve(TenantContextResolver::class)->current();

    return new TriggerRequest(
        context: $context,
        scope: resolve(DataScopeFactory::class)->forCurrentPrincipal($context),
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

function captureWorkflow(TriggerRequest $request): CaptureResult
{
    $connection = DB::connection();

    return $connection->transaction(fn () => resolve(WorkflowBinding::class)->capture($request, $connection));
}

it('captures an immutable intent and replays the same request key', function (): void {
    $first = captureWorkflow(triggerRequest('workflow:test:1'));
    $replay = captureWorkflow(triggerRequest('workflow:test:1'));

    expect($first->status)->toBe(CaptureStatus::Created)
        ->and($replay->status)->toBe(CaptureStatus::Existing)
        ->and($replay->intentRef)->toBe($first->intentRef)
        ->and($replay->digest)->toBe($first->digest)
        ->and($this->app['db']->table('sys_workflow_intents')->where('request_key', 'workflow:test:1')->value('state'))->toBe('pending');
});

it('rejects a changed immutable payload for the same request key', function (): void {
    captureWorkflow(triggerRequest('workflow:test:conflict'));

    expect(captureWorkflow(triggerRequest('workflow:test:conflict', ['status' => 'changed']))->status)->toBe(CaptureStatus::Conflict);
});

it('delegates signal and transition operations to the configured engine adapter', function (): void {
    $binding = resolve(WorkflowBinding::class);
    $context = resolve(TenantContextResolver::class)->current();

    expect($binding->signal(new SignalRequest($context, 'approved', 'ticket:1', JsonDocuments::capture([]), 'workflow:signal:1', new DateTimeImmutable('+1 hour')))->status)->toBe('buffered')
        ->and($binding->transition(new TransitionRequest($context, 'ticket:1', 'approve', 'user:1', JsonDocuments::capture([]), 'workflow:transition:1'))->status)->toBe('completed');
});

it('does not send expired signals to the engine adapter', function (): void {
    $binding = resolve(WorkflowBinding::class);
    $context = resolve(TenantContextResolver::class)->current();

    expect($binding->signal(new SignalRequest($context, 'approved', 'ticket:1', JsonDocuments::capture([]), 'workflow:signal:expired', new DateTimeImmutable('-1 second')))->status)->toBe('expired');
});

it('rejects empty definition and version refs before insert', function (string $definitionRef, string $versionRef): void {
    $context = resolve(TenantContextResolver::class)->current();

    captureWorkflow(new TriggerRequest(
        context: $context,
        scope: resolve(DataScopeFactory::class)->forCurrentPrincipal($context),
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
    expect(captureWorkflow(triggerRequest('workflow:stamp:hash'))->status)->toBe(CaptureStatus::Created);

    $row = $this->app['db']->table('sys_workflow_intents')->where('request_key', 'workflow:stamp:hash')->first();
    $stored = json_decode((string) $row->payload, true);

    expect($stored['identity'])->toMatchArray([
        'schema' => 'workflow-definition/2',
        'point' => 'workflow.trigger',
        'id' => 'axiomasoft/corex-workflow:test.ticket:created',
        'version' => 'version:1',
    ])->and($stored['registryFingerprint'])->toBe(resolve(ModuleRegistry::class)->compiled()->fingerprint)
        ->and($row->payload_hash)->toBe(hash(
            'sha256',
            "workflow-definition/2\0trigger\0workflow:stamp:hash\0".json_encode(
                array_diff_key($stored, ['registryFingerprint' => true]),
                JSON_THROW_ON_ERROR,
            ),
        ))
        ->and(captureWorkflow(triggerRequest('workflow:stamp:hash'))->status)->toBe(CaptureStatus::Existing);
});

it('conflicts when the pinned version does not match the stored identity', function (): void {
    captureWorkflow(triggerRequest('workflow:stamp:version'));
    $changed = triggerRequest('workflow:stamp:version');
    $changed = new TriggerRequest(
        context: $changed->context,
        scope: $changed->scope,
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

    expect(captureWorkflow($changed)->status)->toBe(CaptureStatus::Conflict);
});

it('rejects capture outside the exact live owner connection, and rolls back an accepted intent with its caller', function (): void {
    $binding = resolve(WorkflowBinding::class);
    $request = triggerRequest('workflow:owner-transaction');
    $owner = DB::connection();
    config(['database.connections.foreign' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    $foreign = DB::connection('foreign');

    expect(fn () => $binding->capture($request, $owner))->toThrow(RuntimeException::class, 'active owner transaction');
    expect(fn () => $foreign->transaction(fn () => $binding->capture($request, $foreign)))->toThrow(ScopeContextMismatch::class);

    $owner->beginTransaction();
    expect($binding->capture($request, $owner)->status)->toBe(CaptureStatus::Created);
    $owner->rollBack();
    expect($owner->table('sys_workflow_intents')->where('request_key', $request->requestKey)->exists())->toBeFalse();
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

        public function active(ContextScopeInterface $ctx): array
        {
            return [];
        }

        public function isActive(string $name, ContextScopeInterface $ctx): bool
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
    expect(captureWorkflow(triggerRequest('workflow:stamp:drift'))->status)->toBe(CaptureStatus::Created);

    $registry->fingerprint = 'fingerprint-b';

    expect(captureWorkflow(triggerRequest('workflow:stamp:drift'))->status)->toBe(CaptureStatus::Existing)
        ->and(json_decode((string) $this->app['db']->table('sys_workflow_intents')->where('request_key', 'workflow:stamp:drift')->value('payload'), true)['registryFingerprint'])->toBe('fingerprint-a');
});

it('checks workflow triggers through the public Packman registry projection', function (): void {
    $payload = CompiledRegistry::empty()->toArray();
    $payload['extensions'] = [[
        'point' => 'workflow.trigger', 'id' => 'axiomasoft/corex-workflow:test.ticket:created',
        'owner' => 'axiomasoft/corex-workflow', 'ownerVersion' => '2.0.0', 'payload' => ['event' => 'created'],
    ]];
    $payload['fingerprint'] = CompiledRegistry::hashCanonical($payload['compiler'], $payload['modules'], $payload['extensions'], $payload['replacements']);
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('isActive')->andReturnTrue();
    $registry->shouldReceive('compiled')->andReturn(CompiledRegistry::fromArray($payload));
    $this->app->instance(ModuleRegistry::class, $registry);
    config(['corex-workflow.enforce_declarations' => true]);
    $this->app->forgetInstance(WorkflowOptions::class);

    expect(captureWorkflow(triggerRequest('workflow:packman:declared'))->status)->toBe(CaptureStatus::Created);
});
