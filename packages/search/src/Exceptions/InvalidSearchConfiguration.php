<?php

declare(strict_types=1);

namespace CoreX\Search\Exceptions;

use CoreX\Search\Errors\Enums\SearchErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class InvalidSearchConfiguration extends CodedException
{
    public static function weakCursorSecret(): self
    {
        return new self('An active search profile requires a cursor secret of at least 32 bytes.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: SearchErrorCode::InvalidSearchConfiguration, category: ErrorCategory::Internal, retryable: false);
    }
}
