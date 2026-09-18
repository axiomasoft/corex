<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolResult;

interface ToolInvoker
{
    public function invoke(RequestContext $context, ToolCall $call): ToolResult;
}
