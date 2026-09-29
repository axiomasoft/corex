<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Enums;

/** @internal Bulk status is projected through the bulk operation contract and wire representation. */
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
