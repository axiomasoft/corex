<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Transport;

enum ActorType: string
{
    case Identity = 'identity';
    case Support = 'support';
    case System = 'system';
}
