<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;

interface OperationInvoker
{
    public function invoke(OperationInvocation $invocation): OperationResult;
}
