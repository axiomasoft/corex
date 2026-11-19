<?php

declare(strict_types=1);

namespace CoreX\Access\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum AccessErrorCode: string implements ErrorCode
{
    case ExactVisibilityUnavailable = 'access.exact_visibility_unavailable';
    case ScopeCapabilityMismatch = 'access.scope_capability_mismatch';

    public function value(): string
    {
        return $this->value;
    }
}
