<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Actions;

use CoreX\Context\Data\TenantContext;
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
        $this->access->authorizeMove(data: $data, context: $context);
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
        $connectionName = $connection->getName();

        /** @var Task|null $task */
        $task = Task::on($connectionName)->tap(static fn ($query) => $query->getQuery()->lockForUpdate())->find($data->taskId);
        /** @var Stage|null $stage */
        $stage = Stage::on($connectionName)->find($data->stageId);

        if ($task === null) {
            $this->invalid(field: 'task_id', message: 'Задача не найдена в текущем аккаунте.');
        }

        if ($context->workspace !== null && $context->workspace->id !== $task->workspace_id) {
            $this->invalid(field: 'task_id', message: 'Задача не принадлежит текущему рабочему пространству.');
        }

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
            $completed = $this->completion->apply(task: $task, data: new CompleteTaskData(taskId: $task->id, actor: $data->actor), context: $context);
        }

        $task->save();

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
