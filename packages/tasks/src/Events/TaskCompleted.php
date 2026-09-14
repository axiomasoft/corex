<?php

declare(strict_types=1);

namespace CoreX\Tasks\Events;

use CoreX\Tasks\Models\Task;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class TaskCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Task $task,
    ) {}
}
