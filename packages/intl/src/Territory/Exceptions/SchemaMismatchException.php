<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Intl\Errors\Enums\IntlErrorCode;

#[Api(since: '1.0')]
final class SchemaMismatchException extends CodedException
{
    public function __construct(string $detail = 'Localized field or schema hash mismatch.')
    {
        parent::__construct($detail);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(IntlErrorCode::SchemaMismatchException, ErrorCategory::Conflict, retryable: false);
    }
}
