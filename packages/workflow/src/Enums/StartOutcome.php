<?php

declare(strict_types=1);

namespace CoreX\Workflow\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum StartOutcome: string
{
    case Accepted = 'accepted';
    case Confirmed = 'confirmed';
    case AbsentWithProof = 'absent-with-proof';
    case RetryableBeforeSend = 'retryable-before-send';
    case Permanent = 'permanent';
    case Unknown = 'unknown';
}
