<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum MembershipReason: string
{
    case Removed = 'removed';
    case Suspended = 'suspended';
    case Policy = 'policy';
}
