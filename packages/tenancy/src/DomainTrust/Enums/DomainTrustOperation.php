<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Enums;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum DomainTrustOperation: string
{
    case Issue = 'issue';
    case Verify = 'verify';
    case Revoke = 'revoke';
    case Reassign = 'reassign';
}
