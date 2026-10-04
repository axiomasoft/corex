<?php

declare(strict_types=1);

namespace CoreX\Workflow\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\ExtensionStamp;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\CaptureResult;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class SqlWorkflowBinding implements WorkflowBinding
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly TenantContextResolver $contexts,
        private readonly EngineAdapter $engine,
        private readonly ?ModuleRegistry $modules,
        private readonly bool $enforceDeclarations,
        private readonly ClockInterface $clock,
    ) {}

    public function capture(TriggerRequest $request): CaptureResult
    {
        $this->assertContext($request->context->account->id, $request->context->workspace?->id);
        $this->assertKey($request->requestKey);
        $this->assertTriggerDeclaration($request);
        $stamp = WorkflowDefinitionStamp::fromRequest(
            request: $request,
            registryFingerprint: $this->registryFingerprint(),
        );
        $business = [
            'event_id' => $request->eventId,
            'event_name' => $request->eventName,
            'entity_handle' => $request->entityHandle,
            'scalar_snapshot' => $this->canonicalMap(JsonDocuments::map($request->scalarSnapshot)),
        ];

        return $this->captureIntent(
            requestKey: $request->requestKey,
            kind: 'trigger',
            module: $request->module,
            definitionRef: $request->definitionRef,
            versionRef: $request->versionRef,
            subjectRef: $request->subjectRef,
            business: $business,
            stamp: $stamp,
        );
    }

    public function signal(SignalRequest $request): SignalResult
    {
        $this->assertContext($request->context->account->id, $request->context->workspace?->id);
        $this->assertKey($request->requestKey);

        if ($request->expiresAt <= $this->clock->now()) {
            return new SignalResult(status: 'expired');
        }

        return $this->engine->signal($request);
    }

    public function fire(TransitionRequest $request): TransitionResult
    {
        $this->assertContext($request->context->account->id, $request->context->workspace?->id);
        $this->assertKey($request->requestKey);

        return $this->engine->fire($request);
    }

    /**
     * @param  array<string, mixed>  $business
     */
    private function captureIntent(
        string $requestKey,
        string $kind,
        string $module,
        string $definitionRef,
        string $versionRef,
        string $subjectRef,
        array $business,
        ExtensionStamp $stamp,
    ): CaptureResult {
        $stored = WorkflowDefinitionStamp::storedPayload($business, $stamp);
        $encoded = json_encode($stored, JSON_THROW_ON_ERROR);
        $hash = WorkflowDefinitionStamp::payloadHash(
            kind: $kind,
            requestKey: $requestKey,
            canonical: WorkflowDefinitionStamp::canonicalPayload($stored),
        );

        try {
            $this->connection->table('sys_workflow_intents')->insert([
                'id' => (string) Str::uuid7(),
                'request_key' => $requestKey,
                'payload_hash' => $hash,
                'context_hash' => $this->contextHash(),
                'restore_epoch' => str_repeat('0', 64),
                'module' => $module,
                'kind' => $kind,
                'definition_ref' => $definitionRef,
                'version_ref' => $versionRef,
                'subject_ref' => $subjectRef,
                'payload' => $encoded,
                'state' => 'pending',
                'claim_epoch' => 0,
                'attempt' => 0,
                'generation' => 0,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        } catch (QueryException $exception) {
            $existing = $this->connection->table('sys_workflow_intents')->where('request_key', $requestKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            return new CaptureResult(status: $this->admitExisting(
                existing: $existing,
                kind: $kind,
                requestKey: $requestKey,
                business: $business,
                stamp: $stamp,
                versionedHash: $hash,
            ));
        }

        return new CaptureResult(status: 'created');
    }

    /**
     * @param  array<string, mixed>  $business
     */
    private function admitExisting(object $existing, string $kind, string $requestKey, array $business, ExtensionStamp $stamp, string $versionedHash): string
    {
        $stored = json_decode((string) $existing->payload, true);
        $schema = WorkflowDefinitionStamp::schemaOf(
            stored: is_array($stored) ? $stored : null,
            definitionRef: (string) $existing->definition_ref,
            versionRef: (string) $existing->version_ref,
        );

        if ($schema === 'versioned') {
            $identity = is_array($stored['identity'] ?? null) ? $stored['identity'] : [];
            $sameIdentity = ($identity['id'] ?? null) === $stamp->id
                && ($identity['version'] ?? null) === $stamp->version
                && ($identity['point'] ?? null) === $stamp->point;

            return $sameIdentity && hash_equals((string) $existing->payload_hash, $versionedHash)
                ? 'existing'
                : 'conflict';
        }

        if ($schema === 'legacy') {
            $legacyHash = WorkflowDefinitionStamp::legacyPayloadHash(
                kind: $kind,
                requestKey: $requestKey,
                businessPayload: $business,
            );

            return hash_equals((string) $existing->payload_hash, $legacyHash) ? 'existing' : 'conflict';
        }

        return 'conflict';
    }

    private function registryFingerprint(): ?string
    {
        if ($this->modules === null) {
            return null;
        }

        return $this->modules->compiled()->fingerprint;
    }

    private function assertTriggerDeclaration(TriggerRequest $request): void
    {
        if (! $this->enforceDeclarations) {
            return;
        }

        if ($this->modules === null || ! $this->modules->isActive($request->module, $request->context)) {
            throw new UnsupportedWorkflowCapability('The workflow module is not active for the current tenant.');
        }

        foreach ($this->modules->compiled()->workflow as $workflow) {
            if ($workflow->kind === 'trigger'
                && $workflow->entityHandle === $request->entityHandle
                && in_array($request->eventName, $workflow->eventNames, true)) {
                return;
            }
        }

        throw new UnsupportedWorkflowCapability('The workflow event is not declared by the active module.');
    }

    private function assertContext(string $accountId, ?string $workspaceId): void
    {
        $current = $this->contexts->current();

        if ($current->account->id !== $accountId || $current->workspace?->id !== $workspaceId) {
            throw new RuntimeException('Workflow request context does not match the current tenant context.');
        }
    }

    private function assertKey(string $key): void
    {
        if ($key === '' || strlen($key) > 128) {
            throw new RuntimeException('Workflow request key must be between 1 and 128 bytes.');
        }
    }

    /** @param array<string, mixed> $value
     * @return array<string, mixed> */
    private function canonicalMap(array $value): array
    {
        if (count($value) > 256) {
            throw new RuntimeException('Workflow scalar snapshot must be a bounded object map.');
        }

        ksort($value);

        if (strlen(json_encode($value, JSON_THROW_ON_ERROR)) > 65_536) {
            throw new RuntimeException('Workflow scalar snapshot exceeds 64KiB.');
        }

        return $value;
    }

    private function contextHash(): string
    {
        $context = $this->contexts->current();
        $workspace = $context->workspace;
        $workspaceId = $workspace === null ? '' : $workspace->id;

        return hash('sha256', $context->account->id."\0".$workspaceId);
    }
}
