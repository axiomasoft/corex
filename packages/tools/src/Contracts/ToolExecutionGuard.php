<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ToolExecutionGuard
{
    /**
     * @param  Closure():ToolResult  $effect
     */
    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult;
}
