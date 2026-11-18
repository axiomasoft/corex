<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Exceptions;

use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BudgetStoreUnavailable extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: OperationsErrorCode::BudgetStoreUnavailable, category: ErrorCategory::Internal, retryable: false);
    }
}
