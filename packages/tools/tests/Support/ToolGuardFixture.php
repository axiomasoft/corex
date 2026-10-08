<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use Closure;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/** Consumer guard fixture binding approval, idempotency and resource version for tests. */
final class ToolGuardFixture implements ToolExecutionGuard
{
    public ?string $approvedProofDigest = null;

    public int $resourceVersion = 1;

    public int $requiredResourceVersion = 1;

    public bool $revokeBeforeEffect = false;

    public bool $visibilityRevoked = false;

    public bool $simulateExternalCommit = false;

    public bool $enforceOutputInTransaction = true;

    public int $effectCount = 0;

    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
    {
        if ($this->visibilityRevoked) {
            return $this->denied(errorCode: ToolsErrorCode::Forbidden, call: $call);
        }

        $binding = $this->bindingDigest(tool: $tool, context: $context, call: $call);

        if ($tool->idempotency === 'provider-key' && $tool->sideEffect !== 'read') {
            $approval = $this->assertApproval(binding: $binding, call: $call);

            if ($approval !== null) {
                return $approval;
            }

            return $this->executeProviderKeyed(tool: $tool, call: $call, effect: $effect);
        }

        if ($tool->idempotency === 'domain-key' && $call->operationKey !== null) {
            return $this->executeDomainKeyed(
                tool: $tool,
                context: $context,
                call: $call,
                binding: $binding,
                effect: $effect,
            );
        }

        $approval = $this->assertApproval(binding: $binding, call: $call);

        if ($approval !== null) {
            return $approval;
        }

        $this->effectCount++;

        return $this->finalize(tool: $tool, call: $call, effect: $effect);
    }

    public function bindingDigest(ToolDescriptor $tool, RequestContext $context, ToolCall $call): string
    {
        return hash('sha256', implode('|', [
            $context->principal()->id(),
            $context->tenant()->account->id,
            $context->tenant()->workspace?->id ?? '',
            $tool->module,
            $tool->code,
            $tool->toolVersion,
            $tool->contractHash,
            $call->arguments->canonicalJson,
            $call->operationKey ?? '',
            (string) $this->requiredResourceVersion,
        ]));
    }

    private function executeProviderKeyed(ToolDescriptor $tool, ToolCall $call, Closure $effect): ToolResult
    {
        $operationKey = $call->operationKey ?? hash('sha256', $call->requestId);

        try {
            $this->effectCount++;
            $result = $effect();

            if ($result->outcome === ToolOutcome::Committed) {
                return $result;
            }

            return new ToolResult(
                outcome: ToolOutcome::Unknown,
                structuredData: null,
                errorCode: ToolsErrorCode::UnknownEffect->value,
                operationKey: $operationKey,
                replayed: false,
            );
        } catch (Throwable) {
            return new ToolResult(
                outcome: ToolOutcome::Unknown,
                structuredData: null,
                errorCode: ToolsErrorCode::UnknownEffect->value,
                operationKey: $operationKey,
                replayed: false,
            );
        }
    }

    private function executeDomainKeyed(
        ToolDescriptor $tool,
        RequestContext $context,
        ToolCall $call,
        string $binding,
        Closure $effect,
    ): ToolResult {
        $operationKey = $call->operationKey ?? throw new LogicException('operation key required');

        return DB::transaction(function () use ($tool, $context, $call, $binding, $operationKey, $effect): ToolResult {
            $existing = DB::table('tool_idempotency')
                ->where('operation_key', $operationKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->binding_digest !== $binding) {
                    return $this->denied(errorCode: ToolsErrorCode::IdempotencyConflict, call: $call);
                }

                $payload = json_decode((string) $existing->result_json, true, 512, JSON_THROW_ON_ERROR);

                return new ToolResult(
                    outcome: ToolOutcome::Committed,
                    structuredData: JsonObjectFactory::fromArray($payload),
                    errorCode: null,
                    operationKey: $operationKey,
                    replayed: true,
                );
            }

            $approval = $this->assertApproval(binding: $binding, call: $call);

            if ($approval !== null) {
                return $approval;
            }

            $result = $this->finalize(tool: $tool, call: $call, effect: $effect);

            if ($result->outcome !== ToolOutcome::Committed || $result->structuredData === null) {
                return $result;
            }

            DB::table('tool_idempotency')->insert([
                'operation_key' => $operationKey,
                'binding_digest' => $binding,
                'result_json' => $result->structuredData->canonicalJson,
                'account_id' => $context->tenant()->account->id,
                'created_at' => now(),
            ]);

            return $result;
        });
    }

    private function finalize(ToolDescriptor $tool, ToolCall $call, Closure $effect): ToolResult
    {
        if ($this->requiredResourceVersion !== $this->resourceVersion) {
            return $this->denied(errorCode: ToolsErrorCode::ApprovalInvalid, call: $call);
        }

        if ($this->enforceOutputInTransaction && ! $this->simulateExternalCommit) {
            try {
                return DB::transaction(static function () use ($effect): ToolResult {
                    $result = $effect();

                    if ($result->outcome !== ToolOutcome::Committed) {
                        throw new ToolEffectRollback($result);
                    }

                    return $result;
                });
            } catch (ToolEffectRollback $rollback) {
                return $rollback->result;
            }
        }

        $result = $effect();

        if ($this->simulateExternalCommit && $result->errorCode === ToolsErrorCode::OutputContractFailed->value) {
            return new ToolResult(
                outcome: ToolOutcome::Committed,
                structuredData: null,
                errorCode: ToolsErrorCode::OutputContractFailed->value,
                operationKey: $call->operationKey,
                replayed: false,
            );
        }

        return $result;
    }

    private function assertApproval(string $binding, ToolCall $call): ?ToolResult
    {
        if ($this->approvedProofDigest !== null && $this->approvedProofDigest !== $binding) {
            return $this->denied(errorCode: ToolsErrorCode::ApprovalInvalid, call: $call);
        }

        if ($call->clientProof !== null && $call->clientProof !== $binding) {
            return $this->denied(errorCode: ToolsErrorCode::ApprovalInvalid, call: $call);
        }

        if ($this->revokeBeforeEffect) {
            return $this->denied(errorCode: ToolsErrorCode::ApprovalInvalid, call: $call);
        }

        return null;
    }

    private function denied(ToolsErrorCode $errorCode, ToolCall $call): ToolResult
    {
        return new ToolResult(
            outcome: ToolOutcome::NotExecuted,
            structuredData: null,
            errorCode: $errorCode->value,
            operationKey: $call->operationKey,
            replayed: false,
        );
    }

    public static function argumentsDigest(JsonObject $arguments): string
    {
        return hash('sha256', $arguments->canonicalJson);
    }
}
