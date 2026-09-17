<?php

declare(strict_types=1);

namespace CoreX\Workflow\Internal;

use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tenancy\Contracts\TenantContextResolver;
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
use RuntimeException;

final class SqlWorkflowBinding implements WorkflowBinding
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly TenantContextResolver $contexts,
        private readonly EngineAdapter $engine,
        private readonly ?ModuleRegistry $modules,
        private readonly bool $enforceDeclarations,
    ) {}

    public function capture(TriggerRequest $request): CaptureResult
    {
        $this->assertContext($request->context->account->id, $request->context->workspace?->id);
        $this->assertKey($request->requestKey);
        $this->assertTriggerDeclaration($request);
        $payload = [
            'event_id' => $request->eventId,
            'event_name' => $request->eventName,
            'entity_handle' => $request->entityHandle,
            'scalar_snapshot' => $this->canonicalMap($request->scalarSnapshot),
        ];

        return $this->captureIntent(
            requestKey: $request->requestKey,
            kind: 'trigger',
            module: $request->module,
            definitionRef: $request->definitionRef,
            versionRef: $request->versionRef,
            subjectRef: $request->subjectRef,
            payload: $payload,
        );
    }

    public function signal(SignalRequest $request): SignalResult
    {
        $this->assertContext($request->context->account->id, $request->context->workspace?->id);
        $this->assertKey($request->requestKey);

        if ($request->expiresAt <= now()) {
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

    /** @param array<string, mixed> $payload */
    private function captureIntent(string $requestKey, string $kind, string $module, string $definitionRef, string $versionRef, string $subjectRef, array $payload): CaptureResult
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', "engine-binding/1\0{$kind}\0{$requestKey}\0{$encoded}");

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
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existing = $this->connection->table('sys_workflow_intents')->where('request_key', $requestKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            return new CaptureResult(status: hash_equals((string) $existing->payload_hash, $hash) ? 'existing' : 'conflict');
        }

        return new CaptureResult(status: 'created');
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
