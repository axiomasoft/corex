<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\OperationResult;

interface OperationInvoker
{
    public function invoke(OperationInvocation $invocation): OperationResult;
}
