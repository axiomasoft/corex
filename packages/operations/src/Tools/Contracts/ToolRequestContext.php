<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

interface ToolRequestContext extends RequestContext
{
    public function operationKey(): ?string;

    public function toolContractHash(): string;
}
