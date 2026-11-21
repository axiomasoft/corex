<?php

declare(strict_types=1);

namespace CoreX\Foundation\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum LifecyclePhase: string
{
    case Planned = 'planned';
    case Fenced = 'fenced';
    case Blocked = 'blocked';
    case Draining = 'draining';
    case Cleaning = 'cleaning';
    case PackmanTransition = 'packman-transition';
    case Done = 'done';
}
