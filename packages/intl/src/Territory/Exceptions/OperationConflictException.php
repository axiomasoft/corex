<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Intl\Errors\Enums\IntlErrorCode;

#[Api(since: '1.0')]
final class OperationConflictException extends CodedException
{
    public function __construct(string $detail = 'Concurrent or conflicting operation.')
    {
        parent::__construct($detail);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: IntlErrorCode::OperationConflictException, category: ErrorCategory::Conflict, retryable: false);
    }
}
