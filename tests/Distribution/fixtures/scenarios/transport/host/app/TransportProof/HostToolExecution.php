<?php

declare(strict_types=1);

namespace App\TransportProof;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Illuminate\Database\DatabaseManager;

final readonly class HostToolExecution implements ToolExecutionGuard
{
    public function __construct(
        private OperationRegistry $operations,
        private DataScopeFactory $scopes,
        private CurrentWorkScope $current,
        private WorkScopeRunner $runner,
        private DatabaseManager $database,
    ) {}

    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
    {
        $operation = array_find($this->operations->all(version: 'v2'), static fn (Operation $operation): bool => $tool->operationRef === 'v2:'.$operation->id && $tool->module === $operation->module);

        if (! $context instanceof ClientRequestContext || $context->clientId() === '' || $call->operationKey === null
            || $operation?->effectMode !== 'tenant_transactional' || $tool->idempotency !== 'domain-key') {
            return new ToolResult(outcome: ToolOutcome::NotExecuted, structuredData: null,
                errorCode: ToolsErrorCode::ToolUnavailable, operationKey: $call->operationKey, replayed: false);
        }

        $scope = $this->scopes->forCurrentPrincipal($context->tenant());
        $db = $this->database->connection();

        return $this->runner->run($scope, function () use ($db, $scope, $effect): ToolResult {
            try {
                return $db->transaction(function () use ($db, $scope, $effect): ToolResult {
                    $this->current->assert($scope, $db);
                    $result = $effect();

                    if ($result->outcome !== ToolOutcome::Committed) {
                        throw new AbortedToolOperation($result);
                    }

                    return $result;
                });
            } catch (AbortedToolOperation $aborted) {
                return $aborted->result;
            }
        });
    }
}
