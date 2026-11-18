<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Exceptions;

use CoreX\Recurrence\Errors\Enums\RecurrenceErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class UnsupportedEnvironmentException extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: RecurrenceErrorCode::UnsupportedEnvironmentException, category: ErrorCategory::Unsupported, retryable: false);
    }
}
