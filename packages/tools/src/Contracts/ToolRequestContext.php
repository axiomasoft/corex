<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Operations\Invocation\Contracts\RequestContext;

interface ToolRequestContext extends RequestContext
{
    public function operationKey(): ?string;

    public function toolContractHash(): string;
}
