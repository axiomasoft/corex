<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum TaskCompletionAdmissionStatus: string
{
    case Created = 'created';
    case Existing = 'existing';
    case Conflict = 'conflict';
}
