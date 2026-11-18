<?php

declare(strict_types=1);

namespace CoreX\Intl\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum IntlErrorCode: string implements ErrorCode
{
    case AccessDeniedException = 'intl.access_denied_exception';
    case InvalidLocaleException = 'intl.invalid_locale_exception';
    case InvalidRateException = 'intl.invalid_rate_exception';
    case OperationConflictException = 'intl.operation_conflict_exception';
    case SchemaMismatchException = 'intl.schema_mismatch_exception';
    case SnapshotExpiredException = 'intl.snapshot_expired_exception';
    case UnsupportedFormattingException = 'intl.unsupported_formatting_exception';

    public function value(): string
    {
        return $this->value;
    }
}
