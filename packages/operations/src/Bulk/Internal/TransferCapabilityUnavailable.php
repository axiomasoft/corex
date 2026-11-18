<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Enums\UnavailabilityReason;
use RuntimeException;

/** @internal Fail-closed before a transfer bulk row exists. */
#[Internal]
final class TransferCapabilityUnavailable extends RuntimeException implements DescribesError
{
    public function __construct()
    {
        parent::__construct('Transfer bulk capability is not installed.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: OperationsErrorCode::CapabilityUnavailable,
            category: ErrorCategory::Unavailable,
            retryable: false,
            details: ['reason' => UnavailabilityReason::CapabilityUnavailable->value],
        );
    }
}
