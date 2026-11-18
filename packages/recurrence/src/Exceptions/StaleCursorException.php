<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Exceptions;

use CoreX\Recurrence\Errors\Enums\RecurrenceErrorCode;
use InvalidArgumentException;
use Packman\Attributes\Api;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;

#[Api(since: '1.0')]
final class StaleCursorException extends InvalidArgumentException implements DescribesError
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: RecurrenceErrorCode::StaleCursorException, category: ErrorCategory::Conflict, retryable: false);
    }
}
