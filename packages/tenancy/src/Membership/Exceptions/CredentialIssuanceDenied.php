<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Exceptions;

use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class CredentialIssuanceDenied extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TenancyErrorCode::CredentialIssuanceDenied, category: ErrorCategory::Authorization, retryable: false);
    }
}
