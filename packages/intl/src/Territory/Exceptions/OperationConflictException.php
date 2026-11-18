<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Exceptions;

use CoreX\Intl\Errors\Enums\IntlErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

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
