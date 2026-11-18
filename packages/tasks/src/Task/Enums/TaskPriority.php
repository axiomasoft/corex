<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Enums;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
