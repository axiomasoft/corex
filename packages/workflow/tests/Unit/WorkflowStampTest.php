<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Modules\Data\ExtensionStamp;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Internal\WorkflowDefinitionStamp;

function stampRequest(string $definitionRef = 'definition:1', string $versionRef = 'version:1'): TriggerRequest
{
    return new TriggerRequest(
        context: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'workflow-test', 'active', [], [])),
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
        ->and(WorkflowDefinitionStamp::schemaOf($stored, 'definition:1', 'version:1'))->toBe('versioned');
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

it('keeps the legacy comparison for non-empty refs that have no identity schema', function (): void {
    $business = ['event_name' => 'created'];

    expect(WorkflowDefinitionStamp::schemaOf($business, 'definition:1', 'version:1'))->toBe('legacy')
        ->and(WorkflowDefinitionStamp::legacyPayloadHash('trigger', 'workflow:stamp:1', $business))
        ->not->toBe(WorkflowDefinitionStamp::payloadHash('trigger', 'workflow:stamp:1', $business));
});
