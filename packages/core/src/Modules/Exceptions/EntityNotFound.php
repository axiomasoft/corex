<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class EntityNotFound extends CodedException
{
    public function __construct(string $handle)
    {
        parent::__construct(sprintf('Entity [%s] is not registered.', $handle));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::EntityNotFound, ErrorCategory::NotFound, retryable: false);
    }
}
