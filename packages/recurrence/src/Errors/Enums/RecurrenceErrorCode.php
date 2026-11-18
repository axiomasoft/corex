<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

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
