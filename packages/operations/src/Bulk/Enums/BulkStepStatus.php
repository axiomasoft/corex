<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum BulkStepStatus: string
{
    case Accepted = 'accepted';
    case Progress = 'progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
