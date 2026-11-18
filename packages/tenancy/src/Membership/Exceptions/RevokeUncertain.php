<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Exceptions;

use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class RevokeUncertain extends CodedException
{
    public function __construct(public readonly string $requestId)
    {
        parent::__construct('Central revoke outcome uncertain; retain original request ID and payload.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TenancyErrorCode::RevokeUncertain, category: ErrorCategory::Unavailable, retryable: true);
    }
}
