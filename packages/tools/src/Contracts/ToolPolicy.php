<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Tools\Data\ToolDescriptor;

interface ToolPolicy
{
    public function discover(ToolDescriptor $tool, RequestContext $context): bool;

    public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool;
}
