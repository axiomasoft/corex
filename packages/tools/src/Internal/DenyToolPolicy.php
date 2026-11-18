<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolDescriptor;
use Override;
use Packman\Attributes\Internal;

/** Default deny-all tool policy; consumer adapters may replace the binding. */
#[Internal]
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
