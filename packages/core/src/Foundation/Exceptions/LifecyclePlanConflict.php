<?php

declare(strict_types=1);

namespace CoreX\Foundation\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '2.0.0')]
final class LifecyclePlanConflict extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::LifecyclePlanConflict, ErrorCategory::Conflict, false);
    }
}
