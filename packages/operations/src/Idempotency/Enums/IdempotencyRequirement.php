<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Enums;

/** @internal Use operation declarations, not this projection enum, as the public contract. */
enum IdempotencyRequirement: string
{
    case Forbidden = 'forbidden';
    case Optional = 'optional';
    case Required = 'required';
}
