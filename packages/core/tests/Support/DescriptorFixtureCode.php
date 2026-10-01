<?php

declare(strict_types=1);

namespace CoreX\Testing;

use CoreX\Errors\Contracts\ErrorCode;

enum DescriptorFixtureCode: string implements ErrorCode
{
    case AccessDenied = 'operations.access_denied';

    public function value(): string
    {
        return $this->value;
    }
}
