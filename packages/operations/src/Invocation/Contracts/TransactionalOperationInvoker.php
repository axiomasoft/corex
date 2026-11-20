<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Operations\Cost\Data\BudgetAttempt;
use CoreX\Operations\Cost\Data\BudgetInvocationResult;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface TransactionalOperationInvoker
{
    public function invokeTransactional(OperationInvocation $invocation, BudgetAttempt $attempt): BudgetInvocationResult;
}
