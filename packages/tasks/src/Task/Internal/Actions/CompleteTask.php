<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Actions;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Access\Contracts\TaskModuleContext as TaskModuleGuard;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Events\TaskCompleted;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\Task\Internal\TaskCompletion;
use CoreX\Tasks\Task\Internal\TaskReferenceFactory;
use CoreX\Tasks\Task\Internal\TaskSnapshot;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Validation\ValidationException;
use Psr\Clock\ClockInterface;

final class CompleteTask implements CompleteTaskCommand
{
    public function __construct(
        private readonly TaskModuleGuard $moduleGuard,
        private readonly TaskMutationAccess $access,
        private readonly ClockInterface $clock,
        private readonly Dispatcher $events,
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
        $task = Task::on($connection->getName())->tap(static fn ($query) => $query->getQuery()->lockForUpdate())->find($data->taskId);

        if ($task === null) {
            $this->invalid('task_id', 'Задача не найдена в текущем аккаунте.');
        }

        if ($context->workspace !== null && $context->workspace->id !== $task->workspace_id) {
            $this->invalid('task_id', 'Задача не принадлежит текущему рабочему пространству.');
        }

        if (! (new TaskCompletion($this->access, $this->clock))->apply($task, $data, $context)) {
            return TaskReferenceFactory::fromTask($task);
        }
        $task->save();
        $this->events->dispatch(new TaskCompleted(TaskSnapshot::fromTask($task)));

        return TaskReferenceFactory::fromTask($task);
    }

    /** @return never */
    private function invalid(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
