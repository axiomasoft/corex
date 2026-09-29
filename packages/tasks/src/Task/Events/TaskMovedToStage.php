<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Events;

use CoreX\Tasks\Task\Models\Stage;
use CoreX\Tasks\Task\Models\Task;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class TaskMovedToStage implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Task $task,
        public readonly Stage $stage,
    ) {}
}
