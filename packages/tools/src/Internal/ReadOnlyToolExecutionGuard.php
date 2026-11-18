<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use Closure;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Override;
use Packman\Attributes\Internal;

/** Default guard: admits read tools only; mutations require a consumer guard. */
#[Internal]
final class ReadOnlyToolExecutionGuard implements ToolExecutionGuard
{
    #[Override]
    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
    {
        if ($tool->sideEffect !== 'read') {
            return new ToolResult(
                outcome: ToolOutcome::NotExecuted,
                structuredData: null,
                errorCode: ToolsErrorCode::ApprovalRequired->value,
                operationKey: $call->operationKey,
                replayed: false,
            );
        }

        return $effect();
    }
}
