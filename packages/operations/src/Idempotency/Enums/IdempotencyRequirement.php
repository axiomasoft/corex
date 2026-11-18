<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Enums;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum IdempotencyRequirement: string
{
    case Forbidden = 'forbidden';
    case Optional = 'optional';
    case Required = 'required';
}
