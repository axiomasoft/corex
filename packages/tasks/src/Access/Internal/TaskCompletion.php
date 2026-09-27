<?php

declare(strict_types=1);

namespace CoreX\Tasks\Support;

use CoreX\Tasks\Contracts\TaskMutationAccess;
use CoreX\Tasks\Data\CompleteTaskData;
use CoreX\Tasks\Enums\TaskStatus;
use CoreX\Tasks\Models\Task;
use CoreX\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/** @internal Apply to a locked task; the caller persists it and dispatches after commit. */
final readonly class TaskCompletion
{
    public function __construct(private TaskMutationAccess $access) {}

    public function apply(Task $task, CompleteTaskData $data, TenantContext $context): bool
    {
        $this->access->authorizeComplete(data: $data, context: $context, task: $task);

        if ($task->status === TaskStatus::Done) {
            return false;
        }

        if ($task->status === TaskStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => ['Отменённую задачу нельзя завершить.']]);
        }
        $task->status = TaskStatus::Done;
        $task->completed_at = now();

        return true;
    }
}
