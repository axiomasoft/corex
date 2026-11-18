<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolResult;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
interface ToolInvoker
{
    public function invoke(RequestContext $context, ToolCall $call): ToolResult;
}
