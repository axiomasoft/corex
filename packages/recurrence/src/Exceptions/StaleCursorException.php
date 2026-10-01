<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Recurrence\Errors\Enums\RecurrenceErrorCode;
use InvalidArgumentException;

#[Api(since: '1.0')]
final class StaleCursorException extends InvalidArgumentException implements DescribesError
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(RecurrenceErrorCode::StaleCursorException, ErrorCategory::Conflict, retryable: false);
    }
}
