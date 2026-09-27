<?php

declare(strict_types=1);

namespace CoreX\Tasks\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Review = 'review';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
