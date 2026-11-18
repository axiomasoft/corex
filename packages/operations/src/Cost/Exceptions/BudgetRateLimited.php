<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Exceptions;

use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BudgetRateLimited extends TooManyRequestsHttpException implements DescribesError
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: OperationsErrorCode::BudgetRateLimited, category: ErrorCategory::RateLimit, retryable: false);
    }
}
