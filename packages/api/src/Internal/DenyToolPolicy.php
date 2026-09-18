<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\ToolDescriptor;
use Override;

/** Default deny-all tool policy; consumer adapters may replace the binding. */
final class DenyToolPolicy implements ToolPolicy
{
    #[Override]
    public function discover(ToolDescriptor $tool, RequestContext $context): bool
    {
        return false;
    }

    #[Override]
    public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool
    {
        return false;
    }
}
