<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Enums;

/** @internal */
enum MembershipEventKind: string
{
    case Granted = 'identity.membership.granted';
    case Revoked = 'identity.membership.revoked';
}
