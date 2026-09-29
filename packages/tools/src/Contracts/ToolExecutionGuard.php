<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use Closure;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;

interface ToolExecutionGuard
{
    /**
     * @param  Closure():ToolResult  $effect
     */
    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult;
}
