<?php

declare(strict_types=1);

namespace CoreX\Intl\Formatting\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Intl\Errors\Enums\IntlErrorCode;

#[Api(since: '1.0')]
final class UnsupportedFormattingException extends CodedException
{
    public function __construct(string $detail = 'Requested formatting capability is unavailable.')
    {
        parent::__construct($detail);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(IntlErrorCode::UnsupportedFormattingException, ErrorCategory::Unsupported, retryable: false);
    }
}
