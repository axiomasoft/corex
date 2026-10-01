<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Tools\Data\ToolDescriptor;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ToolPolicy
{
    public function discover(ToolDescriptor $tool, RequestContext $context): bool;

    public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool;
}
