<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\ToolDescriptor;

interface ToolPolicy
{
    public function discover(ToolDescriptor $tool, RequestContext $context): bool;

    public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool;
}
