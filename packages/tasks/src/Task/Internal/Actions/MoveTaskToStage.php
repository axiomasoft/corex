<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Actions;

use CoreX\Context\Data\TenantContext;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskModuleContext as TaskModuleGuard;
use CoreX\Tasks\Project\Contracts\ProjectRead;
use CoreX\Tasks\Project\Data\ProjectView;
use CoreX\Tasks\Task\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\StageView;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Tasks\Task\Events\TaskCompleted;
use CoreX\Tasks\Task\Events\TaskMovedToStage;
use CoreX\Tasks\Task\Internal\Models\Stage;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\Task\Internal\TaskCompletion;
use CoreX\Tasks\Task\Internal\TaskReferenceFactory;
use CoreX\Tasks\Task\Internal\TaskSnapshot;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Validation\ValidationException;
use LogicException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class MoveTaskToStage implements MoveTaskToStageCommand
{
    public function __construct(
        private TaskModuleGuard $moduleGuard,
        private TaskAccess $access,
        private TaskCompletion $completion,
        private ProjectRead $projects,
        private Dispatcher $events,
    ) {}

    public function handle(MoveTaskToStageData $data): TaskReference
    {
        $context = $this->moduleGuard->context();
        $connection = (new Task)->getConnection();

        return $connection->transaction(
            callback: fn (): TaskReference => $this->move(
                data: $data,
                context: $context,
                connection: $connection,
            ),
        );
    }

    private function move(MoveTaskToStageData $data, TenantContext $context, Connection $connection): TaskReference
    {
        ReferenceWrites::enlist($connection, ReferenceWrites::current(), 'axiomasoft/corex-tasks', 'task-move', ['task' => $data->taskId]);
        $connectionName = $connection->getName();

        $scope = $this->completion->scope($context, $connection);
        $this->access->authorizeMove(data: $data, context: $context);
        $task = $this->completion->lockTask($data->taskId, $scope, $connection);

        /** @var Stage|null $stage */
        $stage = Stage::on($connectionName)->find($data->stageId);

        if ($stage === null || $task->project_id === null || $stage->project_id !== $task->project_id) {
            $this->invalid(field: 'stage_id', message: 'Стадия не принадлежит проекту задачи.');
        }

        /** @var ProjectView|null $project */
        $project = $this->projects->find(id: $stage->project_id, connectionName: $connectionName);

        if ($project === null || $project->workspaceId !== $task->workspace_id) {
            $this->invalid(field: 'stage_id', message: 'Проект стадии не принадлежит рабочему пространству задачи.');
        }

        if ($task->stage_id === $stage->id) {
            return TaskReferenceFactory::fromTask($task);
        }

        $task->stage_id = $stage->id;
        $completed = false;

        if ($stage->auto_status === TaskStatus::InProgress) {
            $task->status = TaskStatus::InProgress;
            $task->completed_at = null;
        }

        if ($stage->auto_status === TaskStatus::Done) {
            $completed = $this->completion->apply(
                task: $task,
                data: new CompleteTaskData(taskId: $task->id, actor: $data->actor),
                context: $context,
                scope: $scope,
                connection: $connection,
            );
        }

        if (! $task->save()) {
            throw new LogicException('The task move was rejected by the Task owner.');
        }

        if ($completed) {
            $this->events->dispatch(new TaskCompleted(TaskSnapshot::fromTask($task)));
        }
        $this->events->dispatch(new TaskMovedToStage(task: TaskSnapshot::fromTask($task), stage: new StageView(id: $stage->id, projectId: $stage->project_id, name: $stage->name, isFinal: (bool) $stage->getAttribute('is_final'))));

        return TaskReferenceFactory::fromTask($task);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
