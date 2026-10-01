<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum ActorType: string
{
    case Identity = 'identity';
    case Support = 'support';
    case System = 'system';
}
