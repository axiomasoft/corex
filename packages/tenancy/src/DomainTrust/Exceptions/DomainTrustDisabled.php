<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;

#[Api(since: '1.0')]
final class DomainTrustDisabled extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(TenancyErrorCode::DomainTrustDisabled, ErrorCategory::Unavailable, retryable: false);
    }
}
