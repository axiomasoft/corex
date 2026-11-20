<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use CoreX\Operations\Invocation\Data\OperationResult;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class BudgetInvocationResult
{
    public function __construct(public OperationResult $result, public ?BudgetReceipt $receipt = null) {}
}
