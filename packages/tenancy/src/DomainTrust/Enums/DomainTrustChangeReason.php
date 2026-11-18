<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Enums;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum DomainTrustChangeReason: string
{
    case Verified = 'verified';
    case Revoked = 'revoked';
    case Reassigned = 'reassigned';
}
