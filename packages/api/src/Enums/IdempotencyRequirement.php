<?php

declare(strict_types=1);

namespace CoreX\Api\Enums;

enum IdempotencyRequirement: string
{
    case Forbidden = 'forbidden';
    case Optional = 'optional';
    case Required = 'required';
}
