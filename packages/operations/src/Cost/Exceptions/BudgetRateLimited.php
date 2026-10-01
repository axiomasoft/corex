<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BudgetRateLimited extends TooManyRequestsHttpException implements DescribesError
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(OperationsErrorCode::BudgetRateLimited, ErrorCategory::RateLimit, retryable: false);
    }
}
