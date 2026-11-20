<?php

declare(strict_types=1);

namespace CoreX\Context\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;

#[Api(since: '2.0.0')]
final class ScopeStale extends ScopeContextMismatch
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::ScopeStale, ErrorCategory::Conflict, false);
    }
}
