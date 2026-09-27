<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal;

use CoreX\Contracts\DescribesError;
use CoreX\Data\ErrorDescriptor;
use CoreX\Enums\ErrorCategory;
use CoreX\Enums\UnavailabilityReason;
use RuntimeException;
use Throwable;

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
            code: 'attributes_access_denied',
            category: ErrorCategory::Authorization,
            retryable: false,
            details: ['reason' => UnavailabilityReason::AccessDenied->value],
        );
    }
}
