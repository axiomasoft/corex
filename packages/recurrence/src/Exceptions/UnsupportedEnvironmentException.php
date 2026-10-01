<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Recurrence\Errors\Enums\RecurrenceErrorCode;

#[Api(since: '1.0')]
final class UnsupportedEnvironmentException extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(RecurrenceErrorCode::UnsupportedEnvironmentException, ErrorCategory::Unsupported, retryable: false);
    }
}
