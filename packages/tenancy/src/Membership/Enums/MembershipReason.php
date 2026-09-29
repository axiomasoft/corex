<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Enums;

/** @internal */
enum MembershipReason: string
{
    case Removed = 'removed';
    case Suspended = 'suspended';
    case Policy = 'policy';
}
