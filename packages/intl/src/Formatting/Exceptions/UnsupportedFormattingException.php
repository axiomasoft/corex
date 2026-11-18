<?php

declare(strict_types=1);

namespace CoreX\Intl\Formatting\Exceptions;

use CoreX\Intl\Errors\Enums\IntlErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class UnsupportedFormattingException extends CodedException
{
    public function __construct(string $detail = 'Requested formatting capability is unavailable.')
    {
        parent::__construct($detail);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: IntlErrorCode::UnsupportedFormattingException, category: ErrorCategory::Unsupported, retryable: false);
    }
}
