<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Enums;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum TimeLogSource: string
{
    case Manual = 'manual';
    case Timer = 'timer';
}
