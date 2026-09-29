<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Enums\UnavailabilityReason;
use RuntimeException;

/** @internal Fail-closed before a transfer bulk row exists. */
final class TransferCapabilityUnavailable extends RuntimeException implements DescribesError
{
    public function __construct()
    {
        parent::__construct('Transfer bulk capability is not installed.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: 'capability_unavailable',
            category: ErrorCategory::Unavailable,
            retryable: false,
            details: ['reason' => UnavailabilityReason::CapabilityUnavailable->value],
        );
    }
}
