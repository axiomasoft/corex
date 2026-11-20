<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum FieldOperator: string
{
    case Eq = 'eq';
    case In = 'in';
    case Lt = 'lt';
    case Lte = 'lte';
    case Gt = 'gt';
    case Gte = 'gte';
    case Between = 'between';
    case Contains = 'contains';
    case FullText = 'full-text';
    case Any = 'any';
    case All = 'all';
    case IsNull = 'is-null';
    case IsAbsent = 'is-absent';
}
