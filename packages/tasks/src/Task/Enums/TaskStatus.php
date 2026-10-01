<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Review = 'review';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
