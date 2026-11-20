<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum IndexOutcome: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
