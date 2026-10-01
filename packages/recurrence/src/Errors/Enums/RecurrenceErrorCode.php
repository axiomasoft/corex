<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum RecurrenceErrorCode: string implements ErrorCode
{
    case StaleCursorException = 'recurrence.stale_cursor_exception';
    case UnsupportedEnvironmentException = 'recurrence.unsupported_environment_exception';

    public function value(): string
    {
        return $this->value;
    }
}
