<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolResult;

interface ToolInvoker
{
    public function invoke(RequestContext $context, ToolCall $call): ToolResult;
}
