<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use Closure;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;

interface ToolExecutionGuard
{
    /**
     * @param  Closure(): ToolResult  $effect
     */
    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult;
}
