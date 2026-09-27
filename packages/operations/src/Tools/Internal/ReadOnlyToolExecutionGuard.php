<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use Closure;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;
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
