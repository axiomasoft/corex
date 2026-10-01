<?php

declare(strict_types=1);

namespace CoreX\Observability\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum OperationOutcome: string
{
    case Succeeded = 'succeeded';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
}
