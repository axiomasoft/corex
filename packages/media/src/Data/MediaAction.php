<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum MediaAction: string
{
    case Store = 'store';
    case Read = 'read';
    case Attach = 'attach';
    case Detach = 'detach';
    case Delete = 'delete';
}
