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
use Throwable;

#[Internal]
final class ScopeDenied extends RuntimeException implements DescribesError
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $reasonCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: CustomFieldsErrorCode::AttributesAccessDenied,
            category: ErrorCategory::Authorization,
            retryable: false,
            details: ['reason' => UnavailabilityReason::AccessDenied->value],
        );
    }
}
