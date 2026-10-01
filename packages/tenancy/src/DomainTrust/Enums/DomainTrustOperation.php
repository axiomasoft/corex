<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum DomainTrustOperation: string
{
    case Issue = 'issue';
    case Verify = 'verify';
    case Revoke = 'revoke';
    case Reassign = 'reassign';
}
