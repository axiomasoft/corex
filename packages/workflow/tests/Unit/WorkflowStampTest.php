<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Execution\Internal\WorkflowDefinitionStamp;
use Packman\Modules\Data\Compiler\ExtensionStamp;

function stampRequest(string $definitionRef = 'definition:1', string $versionRef = 'version:1'): TriggerRequest
{
    return new TriggerRequest(
        context: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'workflow-test', 'active', [], [])),
        scope: new DataScope(
            'fixture-deployment', '11111111-1111-7111-8111-111111111111', null,
            'fixture-binding', 1, 'fixture-incarnation', str_repeat('a', 64), 'user', 'actor',
        ),
        module: 'axiomasoft/corex-workflow',
        entityHandle: 'test.ticket',
        eventId: 'event:1',
        eventName: 'created',
        subjectRef: 'ticket:1',
        definitionRef: $definitionRef,
        versionRef: $versionRef,
        scalarSnapshot: JsonDocuments::capture(['status' => 'new']),
        requestKey: 'workflow:stamp:1',
    );
}

it('derives the trigger identity and keeps the fingerprint out of the replay hash', function (): void {
    $stamp = WorkflowDefinitionStamp::fromRequest(stampRequest(), 'fingerprint-a');
    $business = ['event_id' => 'event:1', 'scalar_snapshot' => ['status' => 'new']];
    $stored = WorkflowDefinitionStamp::storedPayload($business, $stamp);
    $drifted = WorkflowDefinitionStamp::storedPayload($business, new ExtensionStamp(
        point: $stamp->point,
        id: $stamp->id,
        version: $stamp->version,
        registryFingerprint: 'fingerprint-b',
    ));

    expect($stamp->point)->toBe('workflow.trigger')
        ->and($stamp->id)->toBe('axiomasoft/corex-workflow:test.ticket:created')
        ->and($stamp->version)->toBe('version:1')
        ->and($stored['registryFingerprint'])->toBe('fingerprint-a')
        ->and(WorkflowDefinitionStamp::payloadHash('trigger', 'workflow:stamp:1', WorkflowDefinitionStamp::canonicalPayload($stored)))
        ->toBe(WorkflowDefinitionStamp::payloadHash('trigger', 'workflow:stamp:1', WorkflowDefinitionStamp::canonicalPayload($drifted)))
        ->and(WorkflowDefinitionStamp::matches($stored, $stamp))->toBeTrue();
});

it('uses module-free provenance when no registry fingerprint is available', function (): void {
    $stamp = WorkflowDefinitionStamp::fromRequest(stampRequest(), null);

    expect($stamp->registryFingerprint)->toBe('module-free');
});

it('rejects empty definition and version refs before a stamp is built', function (string $definitionRef, string $versionRef): void {
    WorkflowDefinitionStamp::fromRequest(stampRequest($definitionRef, $versionRef), 'module-free');
})->with([
    'empty definition' => ['', 'version:1'],
    'blank version' => ['definition:1', ' '],
])->throws(InvalidArgumentException::class);

it('rejects a missing identity schema despite pinned ref strings', function (): void {
    $stamp = WorkflowDefinitionStamp::fromRequest(stampRequest(), 'fingerprint-a');

    expect(WorkflowDefinitionStamp::matches(['event_name' => 'created'], $stamp))->toBeFalse();
});
