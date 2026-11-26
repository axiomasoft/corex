<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use Packman\Attributes\Internal;

#[Internal]
enum DeliveryState: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Sending = 'sending';
    case RetryScheduled = 'retry-scheduled';
    case Unknown = 'unknown';
    case Quarantined = 'quarantined';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case PermanentlyFailed = 'permanently-failed';
    case Exhausted = 'exhausted';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    /** @return list<self> Only outcomes that cannot advance through provider reconciliation. */
    public static function finalStates(): array
    {
        return [self::Completed, self::PermanentlyFailed, self::Exhausted, self::Skipped, self::Cancelled];
    }
}
