<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Invocation\Contracts\RequestContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ToolRequestContext extends RequestContext
{
    public function operationKey(): ?string;

    public function toolContractHash(): string;
}
