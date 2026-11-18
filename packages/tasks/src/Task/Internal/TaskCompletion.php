<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Tasks\Task\Internal\Models\Task;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/** @internal Apply to a locked task; the caller persists it and dispatches after commit. */
#[Internal]
final readonly class TaskCompletion
{
    public function __construct(private TaskMutationAccess $access, private ClockInterface $clock) {}

    public function apply(Task $task, CompleteTaskData $data, TenantContext $context): bool
    {
        $this->access->authorizeComplete(data: $data, context: $context, task: TaskSnapshot::fromTask($task));

        if ($task->status === TaskStatus::Done) {
            return false;
        }

        if ($task->status === TaskStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => ['Отменённую задачу нельзя завершить.']]);
        }
        $task->status = TaskStatus::Done;
        $task->completed_at = $this->clock->now();

        return true;
    }
}
