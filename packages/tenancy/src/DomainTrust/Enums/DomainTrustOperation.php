<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Enums;

enum DomainTrustOperation: string
{
    case Issue = 'issue';
    case Verify = 'verify';
    case Revoke = 'revoke';
    case Reassign = 'reassign';
}
