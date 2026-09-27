<?php

declare(strict_types=1);

namespace CoreX\Api\Enums;

/** @internal Use operation declarations, not this projection enum, as the public contract. */
enum IdempotencyRequirement: string
{
    case Forbidden = 'forbidden';
    case Optional = 'optional';
    case Required = 'required';
}
