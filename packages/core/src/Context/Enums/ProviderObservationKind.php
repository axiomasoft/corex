<?php

declare(strict_types=1);

namespace CoreX\Context\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum ProviderObservationKind: string
{
    case Accepted = 'accepted';
    case Confirmed = 'confirmed';
    case Absent = 'absent';
    case Permanent = 'permanent';
    case Unknown = 'unknown';
}
