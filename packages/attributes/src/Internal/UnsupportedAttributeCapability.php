<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal;

use CoreX\Contracts\DescribesError;
use CoreX\Data\ErrorDescriptor;
use CoreX\Enums\ErrorCategory;
use CoreX\Enums\UnavailabilityReason;
use RuntimeException;

final class UnsupportedAttributeCapability extends RuntimeException implements DescribesError
{
    public function __construct()
    {
        parent::__construct('No qualified CoreX attributes engine is installed.', 0);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: 'attributes_engine_unavailable',
            category: ErrorCategory::Unavailable,
            retryable: false,
            details: ['reason' => UnavailabilityReason::CapabilityUnavailable->value],
        );
    }
}
