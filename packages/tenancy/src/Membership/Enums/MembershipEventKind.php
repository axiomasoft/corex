<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum MembershipEventKind: string
{
    case Granted = 'identity.membership.granted';
    case Revoked = 'identity.membership.revoked';
}
