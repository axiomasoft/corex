<?php

declare(strict_types=1);

namespace CoreX\Tasks\Actions;

use CoreX\Tasks\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Contracts\TaskMutationAccess;
use CoreX\Tasks\Data\CompleteTaskData;
use CoreX\Tasks\Data\TaskReference;
use CoreX\Tasks\Enums\TaskStatus;
use CoreX\Tasks\Events\TaskCompleted;
use CoreX\Tasks\Models\Task;
use CoreX\Tasks\Support\TaskModuleGuard;
use CoreX\Tenancy\TenantContext;
use Illuminate\Database\Connection;
use Illuminate\Validation\ValidationException;

final class CompleteTask implements CompleteTaskCommand
{
    public function __construct(
        private readonly TaskModuleGuard $moduleGuard,
        private readonly TaskMutationAccess $access,
    ) {}

    public function handle(CompleteTaskData $data): TaskReference
    {
        $context = $this->moduleGuard->context();
        $connection = (new Task)->getConnection();

        return $connection->transaction(
            callback: fn (): TaskReference => $this->complete(
                data: $data,
                context: $context,
                connection: $connection,
            ),
        );
    }

    private function complete(CompleteTaskData $data, TenantContext $context, Connection $connection): TaskReference
    {
        /** @var Task|null $task */
        $task = Task::on($connection->getName())->lockForUpdate()->find($data->taskId);

        if ($task === null) {
            $this->invalid('task_id', 'Задача не найдена в текущем аккаунте.');
        }

        if ($context->workspace !== null && $context->workspace->id !== $task->workspace_id) {
            $this->invalid('task_id', 'Задача не принадлежит текущему рабочему пространству.');
        }

        $this->access->authorizeComplete(data: $data, context: $context, task: $task);

        if ($task->status === TaskStatus::Done) {
            return TaskReference::fromTask($task);
        }

        if ($task->status === TaskStatus::Cancelled) {
            $this->invalid('status', 'Отменённую задачу нельзя завершить.');
        }

        $task->status = TaskStatus::Done;
        $task->completed_at = now();
        $task->save();
        TaskCompleted::dispatch($task);

        return TaskReference::fromTask($task);
    }

    /** @return never */
    private function invalid(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
