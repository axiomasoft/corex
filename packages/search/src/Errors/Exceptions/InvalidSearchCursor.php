<?php

declare(strict_types=1);

namespace CoreX\Search\Errors\Exceptions;

use CoreX\Search\Errors\Enums\SearchErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '2.0.0')]
final class InvalidSearchCursor extends CodedException
{
    public static function invalid(): self
    {
        return new self('Invalid search cursor.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: SearchErrorCode::InvalidSearchCursor, category: ErrorCategory::Validation, retryable: false);
    }
}
