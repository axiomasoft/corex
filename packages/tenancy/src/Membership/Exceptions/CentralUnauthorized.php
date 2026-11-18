<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Exceptions;

use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
use DomainException;
use Packman\Attributes\Api;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;

#[Api(since: '1.0')]
final class CentralUnauthorized extends DomainException implements DescribesError
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TenancyErrorCode::CentralUnauthorized, category: ErrorCategory::Authorization, retryable: false);
    }
}
