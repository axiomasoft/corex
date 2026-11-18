<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Tools\Data\ToolDescriptor;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ToolPolicy
{
    public function discover(ToolDescriptor $tool, RequestContext $context): bool;

    public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool;
}
