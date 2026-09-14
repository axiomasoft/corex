<?php

declare(strict_types=1);

namespace CoreX\Tasks\Actions;

use CoreX\Tasks\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Data\CompleteTaskData;
use CoreX\Tasks\Data\MoveTaskToStageData;
use CoreX\Tasks\Data\TaskReference;
use CoreX\Tasks\Enums\TaskStatus;
use CoreX\Tasks\Events\TaskCompleted;
use CoreX\Tasks\Events\TaskMovedToStage;
use CoreX\Tasks\Models\Project;
use CoreX\Tasks\Models\Stage;
use CoreX\Tasks\Models\Task;
use CoreX\Tasks\Support\TaskCompletion;
use CoreX\Tasks\Support\TaskModuleGuard;
use CoreX\Tenancy\TenantContext;
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

        /** @var Project|null $project */
        $project = Project::on($connectionName)->find($stage->project_id);

        if ($project === null || $project->workspace_id !== $task->workspace_id) {
            $this->invalid('stage_id', 'Проект стадии не принадлежит рабочему пространству задачи.');
        }

        if ($task->stage_id === $stage->getKey()) {
            return TaskReference::fromTask($task);
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
            TaskCompleted::dispatch($task);
        }
        TaskMovedToStage::dispatch($task, $stage);

        return TaskReference::fromTask($task);
    }

    /** @return never */
    private function invalid(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
