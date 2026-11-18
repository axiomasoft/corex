<?php

declare(strict_types=1);

namespace CoreX\Storage\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class StorageOperationFailed extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::StorageOperationFailed, category: ErrorCategory::Unavailable, retryable: true);
    }
}
