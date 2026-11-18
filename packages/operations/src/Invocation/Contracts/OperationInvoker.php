<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
interface OperationInvoker
{
    public function invoke(OperationInvocation $invocation): OperationResult;
}
