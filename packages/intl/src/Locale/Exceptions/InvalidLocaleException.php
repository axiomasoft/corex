<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Intl\Errors\Enums\IntlErrorCode;

#[Api(since: '1.0')]
final class InvalidLocaleException extends CodedException
{
    public function __construct(string $detail = 'Invalid or unsupported BCP47 locale tag.')
    {
        parent::__construct($detail);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(IntlErrorCode::InvalidLocaleException, ErrorCategory::Validation, retryable: false);
    }
}
