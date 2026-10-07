<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Intl\Errors\Enums\IntlErrorCode;

#[Api(since: '1.0')]
final class InvalidRateException extends CodedException
{
    public function __construct(string $detail = 'Invalid or malformed rate snapshot.')
    {
        parent::__construct($detail);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: IntlErrorCode::InvalidRateException, category: ErrorCategory::Validation, retryable: false);
    }
}
