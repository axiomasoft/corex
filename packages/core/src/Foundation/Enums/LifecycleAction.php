<?php

declare(strict_types=1);

namespace CoreX\Foundation\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum LifecycleAction: string
{
    case Disable = 'disable';
    case Detach = 'detach';
    case Purge = 'purge';
}
