<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use Closure;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;
use Override;

/** Default guard: admits read tools only; mutations require a consumer guard. */
final class ReadOnlyToolExecutionGuard implements ToolExecutionGuard
{
    #[Override]
    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
    {
        if ($tool->sideEffect !== 'read') {
            return new ToolResult(
                outcome: ToolResult::OUTCOME_NOT_EXECUTED,
                structuredData: null,
                errorCode: 'approval_required',
                operationKey: $call->operationKey,
                replayed: false,
            );
        }

        return $effect();
    }
}
