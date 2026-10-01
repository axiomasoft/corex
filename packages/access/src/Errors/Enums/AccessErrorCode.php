<?php

declare(strict_types=1);

namespace CoreX\Access\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum AccessErrorCode: string implements ErrorCode
{
    case ScopeCapabilityMismatch = 'access.scope_capability_mismatch';

    public function value(): string
    {
        return $this->value;
    }
}
