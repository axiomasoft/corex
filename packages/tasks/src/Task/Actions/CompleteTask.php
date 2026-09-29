<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Actions;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Access\Internal\TaskCompletion;
use CoreX\Tasks\Access\Internal\TaskModuleGuard;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Events\TaskCompleted;
use CoreX\Tasks\Task\Models\Task;
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

        if (! (new TaskCompletion($this->access))->apply($task, $data, $context)) {
            return TaskReference::fromTask($task);
        }
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
