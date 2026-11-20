<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum StatisticStatus: string
{
    case Exact = 'exact';
    case Unavailable = 'unavailable';
    case NotRequested = 'not-requested';
}
