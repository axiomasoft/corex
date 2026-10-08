<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Invocation\Contracts\RequestContext;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ToolRequestContext extends RequestContext
{
    public function operationKey(): ?string;

    public function toolContractHash(): string;
}
