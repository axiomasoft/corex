<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

use Packman\Attributes\Api;

/** Whether the guarded tool effect was executed and its result is known. */
#[Api(since: '1.0')]
enum ToolOutcome: string
{
    case NotExecuted = 'not-executed';

    case Committed = 'committed';

    case Unknown = 'unknown';
}
