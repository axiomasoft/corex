<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
use DomainException;

#[Api(since: '1.0')]
final class CentralUnauthorized extends DomainException implements DescribesError
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TenancyErrorCode::CentralUnauthorized, category: ErrorCategory::Authorization, retryable: false);
    }
}
