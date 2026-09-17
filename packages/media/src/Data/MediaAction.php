<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

enum MediaAction: string
{
    case Store = 'store';
    case Read = 'read';
    case Attach = 'attach';
    case Detach = 'detach';
    case Delete = 'delete';
}
