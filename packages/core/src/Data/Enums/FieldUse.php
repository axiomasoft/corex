<?php

declare(strict_types=1);

namespace CoreX\Data\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum FieldUse: string
{
    case Read = 'read';
    case Write = 'write';
    case Search = 'search';
    case Filter = 'filter';
    case Sort = 'sort';
    case Aggregate = 'aggregate';
    case Export = 'export';
}
