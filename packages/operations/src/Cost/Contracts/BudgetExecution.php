<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use Closure;
use CoreX\Operations\Cost\Data\BudgetAttempt;
use CoreX\Operations\Cost\Data\BudgetInvocationResult;
use CoreX\Operations\Cost\Data\BudgetReceipt;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface BudgetExecution
{
    /** @param Closure(Closure():void):OperationResult $effect */
    public function invoke(OperationInvocation $invocation, Closure $effect): OperationResult;

    /** @param Closure(Closure():void):OperationResult $effect */
    public function invokeTransactional(OperationInvocation $invocation, Closure $effect, BudgetAttempt $attempt): BudgetInvocationResult;

    public function recover(BudgetReceipt $receipt): void;
}
