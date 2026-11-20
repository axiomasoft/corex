<?php

declare(strict_types=1);

namespace CoreX\Data\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum FieldUsePlanKind: string
{
    case Unconditional = 'unconditional';
    case ScopedPredicate = 'scoped-predicate';
    case Denied = 'denied';
}
