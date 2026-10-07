<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Intl\Errors\Enums\IntlErrorCode;

#[Api(since: '1.0')]
final class SnapshotExpiredException extends CodedException
{
    public function __construct(string $detail = 'Pinned reference snapshot is no longer available.')
    {
        parent::__construct($detail);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: IntlErrorCode::SnapshotExpiredException, category: ErrorCategory::NotFound, retryable: false);
    }
}
