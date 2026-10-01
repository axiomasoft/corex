<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal;

use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Internal\Models\Task;

/** @internal */
final class TaskReferenceFactory
{
    public static function fromTask(Task $task): TaskReference
    {
        return new TaskReference(id: $task->id, type: TaskReference::TYPE, display: $task->title);
    }
}
