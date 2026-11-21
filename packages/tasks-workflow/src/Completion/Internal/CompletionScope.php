<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Completion\Internal;

use Packman\Attributes\Internal;

#[Internal]
final class CompletionScope
{
    public bool $attachingGate = false;
}
