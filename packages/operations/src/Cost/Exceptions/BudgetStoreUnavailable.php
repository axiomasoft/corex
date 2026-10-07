<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BudgetStoreUnavailable extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: OperationsErrorCode::BudgetStoreUnavailable, category: ErrorCategory::Internal, retryable: false);
    }
}
