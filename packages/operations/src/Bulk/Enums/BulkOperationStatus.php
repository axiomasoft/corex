<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Enums;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum BulkOperationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Completed, self::Failed, self::Expired], true);
    }
}
