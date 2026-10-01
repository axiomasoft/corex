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
use Illuminate\Database\Connection;
use Illuminate\Validation\ValidationException;

final class MoveTaskToStage implements MoveTaskToStageCommand
{
    public function __construct(
        private readonly TaskModuleGuard $moduleGuard,
        private readonly TaskAccess $access,
        private readonly ?TaskCompletion $completion = null,
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
        $task = Task::on($connectionName)->lockForUpdate()->find($data->taskId);
        /** @var Stage|null $stage */
        $stage = Stage::on($connectionName)->find($data->stageId);

        if ($task === null) {
            $this->invalid('task_id', 'Задача не найдена в текущем аккаунте.');
        }

        if ($context->workspace !== null && $context->workspace->id !== $task->workspace_id) {
            $this->invalid('task_id', 'Задача не принадлежит текущему рабочему пространству.');
        }

        if ($stage === null || $task->project_id === null || $stage->project_id !== $task->project_id) {
            $this->invalid('stage_id', 'Стадия не принадлежит проекту задачи.');
        }

        /** @var ProjectView|null $project */
        $project = app(ProjectRead::class)->find($stage->project_id, $connectionName);

        if ($project === null || $project->workspaceId !== $task->workspace_id) {
            $this->invalid('stage_id', 'Проект стадии не принадлежит рабочему пространству задачи.');
        }

        if ($task->stage_id === $stage->getKey()) {
            return TaskReferenceFactory::fromTask($task);
        }

        $task->stage_id = $stage->getKey();
        $completed = false;

        if ($stage->auto_status === TaskStatus::InProgress) {
            $task->status = TaskStatus::InProgress;
            $task->completed_at = null;
        }

        if ($stage->auto_status === TaskStatus::Done) {
            $completed = ($this->completion ?? app(TaskCompletion::class))->apply($task, new CompleteTaskData(taskId: $task->getKey(), actor: $data->actor), $context);
        }

        $task->save();

        if ($completed) {
            event(new TaskCompleted(TaskSnapshot::fromTask($task)));
        }
        event(new TaskMovedToStage(TaskSnapshot::fromTask($task), new StageView($stage->id, $stage->project_id, $stage->name, (bool) $stage->getAttribute('is_final'))));

        return TaskReferenceFactory::fromTask($task);
    }

    /** @return never */
    private function invalid(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
