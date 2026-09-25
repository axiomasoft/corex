<?php

declare(strict_types=1);

namespace CoreX\Observability;

enum OperationOutcome: string
{
    case Succeeded = 'succeeded';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
}
