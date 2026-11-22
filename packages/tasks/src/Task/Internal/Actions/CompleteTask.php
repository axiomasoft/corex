<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Actions;

use CoreX\Context\Data\TenantContext;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Tasks\Access\Contracts\TaskModuleContext as TaskModuleGuard;
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
use LogicException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CompleteTask implements CompleteTaskCommand
{
    public function __construct(
        private TaskModuleGuard $moduleGuard,
        private TaskCompletion $completion,
        private Dispatcher $events,
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
        ReferenceWrites::enlist(connection: $connection, scope: ReferenceWrites::current(), producer: 'axiomasoft/corex-tasks', kind: 'task-complete', witness: ['task' => $data->taskId]);
        $scope = $this->completion->scope(context: $context, connection: $connection);
        $task = $this->completion->lockTask(taskId: $data->taskId, scope: $scope, connection: $connection);

        if (! $this->completion->apply(task: $task, data: $data, context: $context, scope: $scope, connection: $connection)) {
            return TaskReferenceFactory::fromTask($task);
        }

        if (! $task->save()) {
            throw new LogicException('The task completion was rejected by the Task owner.');
        }
        $this->events->dispatch(new TaskCompleted(TaskSnapshot::fromTask($task)));

        return TaskReferenceFactory::fromTask($task);
    }
}
