<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Enums\UnavailabilityReason;
use RuntimeException;

#[Internal]
final class UnsupportedAttributeCapability extends RuntimeException implements DescribesError
{
    public function __construct()
    {
        parent::__construct('No qualified CoreX attributes engine is installed.', 0);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: CustomFieldsErrorCode::AttributesEngineUnavailable,
            category: ErrorCategory::Unavailable,
            retryable: false,
            details: ['reason' => UnavailabilityReason::CapabilityUnavailable->value],
        );
    }
}
