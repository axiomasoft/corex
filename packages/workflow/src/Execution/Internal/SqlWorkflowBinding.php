<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\CaptureResult;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Compiler\ExtensionStamp;
use Psr\Clock\ClockInterface;
use RuntimeException;

#[Internal]
final readonly class SqlWorkflowBinding implements WorkflowBinding
{
    public function __construct(
        private CurrentWorkScope $scopes,
        private WorkflowIntentStore $intents,
        private TenantContextResolver $contexts,
        private EngineAdapter $engine,
        private ?ModuleRegistry $modules,
        private bool $enforceDeclarations,
        private ClockInterface $clock,
    ) {}

    public function capture(TriggerRequest $request, ConnectionInterface $connection): CaptureResult
    {
        if ($connection->transactionLevel() < 1) {
            throw new RuntimeException('Workflow capture requires the caller’s active owner transaction.');
        }

        $trusted = $this->scopes->assert($request->scope, $connection);
        $this->assertContext(accountId: $request->context->account->id, workspaceId: $request->context->workspace?->id);
        $this->assertKey($request->requestKey);

        if ($request->effectKey !== null) {
            throw new RuntimeException('The workflow owner assigns the engine effect key.');
        }

        $request = new TriggerRequest(
            $trusted, $request->scope, $request->module, $request->entityHandle, $request->eventId,
            $request->eventName, $request->subjectRef, $request->definitionRef, $request->versionRef,
            $request->scalarSnapshot, $request->requestKey,
        );
        $stamp = $this->definitionStamp($request);
        $provider = $this->engine->provider($request)
            ?? throw new UnsupportedWorkflowCapability('The requested workflow definition and version have no active engine.');
        $stored = WorkflowDefinitionStamp::storedPayload([
            'event_id' => $request->eventId,
            'event_name' => $request->eventName,
            'entity_handle' => $request->entityHandle,
            'scalar_snapshot' => $this->canonicalMap(JsonDocuments::map($request->scalarSnapshot)),
            'definition_ref' => $request->definitionRef,
            'subject_ref' => $request->subjectRef,
            'provider_namespace' => $provider->namespace,
            'principal_kind' => $request->scope->principalKind,
            'principal_id' => $request->scope->principalId,
        ], $stamp);

        return $this->intents->capture($connection, $request, $provider, $stored);
    }

    public function definitionStamp(TriggerRequest $request): ExtensionStamp
    {
        $this->assertTriggerDeclaration($request);

        return WorkflowDefinitionStamp::fromRequest($request, $this->modules?->compiled()->fingerprint);
    }

    public function signal(SignalRequest $request): SignalResult
    {
        $this->assertContext(accountId: $request->context->account->id, workspaceId: $request->context->workspace?->id);
        $this->assertKey($request->requestKey);

        if ($request->expiresAt <= $this->clock->now()) {
            return new SignalResult(status: 'expired');
        }

        return $this->engine->signal($request);
    }

    public function fire(TransitionRequest $request): TransitionResult
    {
        $this->assertContext(accountId: $request->context->account->id, workspaceId: $request->context->workspace?->id);
        $this->assertKey($request->requestKey);

        return $this->engine->fire($request);
    }

    private function assertTriggerDeclaration(TriggerRequest $request): void
    {
        if (! $this->enforceDeclarations) {
            return;
        }

        if ($this->modules === null || ! $this->modules->isActive(name: $request->module, ctx: $request->context)) {
            throw new UnsupportedWorkflowCapability('The workflow module is not active for the current tenant.');
        }

        $id = $request->module.':'.$request->entityHandle.':'.$request->eventName;
        foreach ($this->modules->compiled()->extensions as $extension) {
            if (($extension['point'] ?? null) === WorkflowDefinitionStamp::POINT
                && ($extension['id'] ?? null) === $id && ($extension['owner'] ?? null) === $request->module) {
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
        if (count($value) > 256 || array_any($value, static fn (mixed $item): bool => $item !== null && ! is_scalar($item))) {
            throw new RuntimeException('Workflow scalar snapshot must be a bounded object map.');
        }

        ksort($value);

        if (strlen(json_encode($value, JSON_THROW_ON_ERROR)) > 65_536) {
            throw new RuntimeException('Workflow scalar snapshot exceeds 64KiB.');
        }

        return $value;
    }
}
