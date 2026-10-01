<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal;

use CoreX\Tasks\Task\Data\TaskView;
use CoreX\Tasks\Task\Internal\Models\Task;

/** @internal Capture explicitly selected scalar fields from the locked model. */
final class TaskSnapshot
{
    public static function fromTask(Task $task): TaskView
    {
        return new TaskView(
            id: $task->id,
            workspaceId: $task->workspace_id,
            projectId: $task->project_id,
            stageId: $task->stage_id,
            title: $task->title,
            status: $task->status,
            authorId: $task->author_id,
            assigneeId: $task->assignee_id,
            requiresAcceptance: $task->requires_acceptance,
        );
    }
}
