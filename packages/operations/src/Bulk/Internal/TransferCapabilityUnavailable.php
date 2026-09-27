<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Bulk;

use CoreX\Contracts\DescribesError;
use CoreX\Data\ErrorDescriptor;
use CoreX\Enums\ErrorCategory;
use CoreX\Enums\UnavailabilityReason;
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
