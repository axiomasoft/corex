<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum DeliveryOutcomeKind: string
{
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Retryable = 'retryable';
    case Permanent = 'permanent';
    case Unknown = 'unknown';
}
