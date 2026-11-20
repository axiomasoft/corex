<?php

declare(strict_types=1);

namespace CoreX\Workflow\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum CaptureStatus: string
{
    case Created = 'created';
    case Existing = 'existing';
    case Conflict = 'conflict';
}
