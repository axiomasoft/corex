<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Actions;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Access\Internal\TaskModuleGuard;
use CoreX\Tasks\Project\Models\Project;
use CoreX\Tasks\Task\Contracts\UpdateTaskCommand;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Data\UpdateTaskData;
use CoreX\Tasks\Task\Events\TaskUpdated;
use CoreX\Tasks\Task\Models\Task;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UpdateTask implements UpdateTaskCommand
{
    public function __construct(
        private readonly TaskModuleGuard $moduleGuard,
        private readonly TaskMutationAccess $access,
    ) {}

    public function handle(UpdateTaskData $data): TaskReference
    {
        $context = $this->moduleGuard->context();
        $connection = (new Task)->getConnection();

        return $connection->transaction(
            callback: fn (): TaskReference => $this->update(
                data: $data,
                context: $context,
                connection: $connection,
            ),
        );
    }

    private function update(UpdateTaskData $data, TenantContext $context, Connection $connection): TaskReference
    {
        $this->validateProvidedFields($data);
        $connectionName = $connection->getName();

        /** @var Task|null $task */
        $task = Task::on($connectionName)->lockForUpdate()->find($data->taskId);

        if ($task === null) {
            $this->invalid('task_id', 'Задача не найдена в текущем аккаунте.');
        }

        if ($context->workspace !== null && $context->workspace->id !== $task->workspace_id) {
            $this->invalid('task_id', 'Задача не принадлежит текущему рабочему пространству.');
        }

        $this->access->authorizeUpdate(data: $data, context: $context, task: $task);

        if ($data->has('title')) {
            $title = trim((string) $data->title);

            if ($title === '' || mb_strlen($title) > 255) {
                $this->invalid('title', 'Название задачи должно содержать от 1 до 255 символов.');
            }

            $task->title = $title;
        }

        if ($data->has('description')) {
            $task->description = $data->description;
        }

        if ($data->has('priority')) {
            if ($data->priority === null) {
                $this->invalid('priority', 'Приоритет задачи обязателен.');
            }

            $task->priority = $data->priority;
        }

        if ($data->has('assignee_id')) {
            if ($data->assigneeId !== null && (! Str::isUuid($data->assigneeId) || ! $connection->table('users')->where('id', $data->assigneeId)->exists())) {
                $this->invalid('assignee_id', 'Пользователь не найден в текущем аккаунте.');
            }

            $task->assignee_id = $data->assigneeId;
        }

        if ($data->has('project_id')) {
            $project = $this->project(
                id: $data->projectId,
                workspaceId: (string) $task->workspace_id,
                connectionName: $connectionName,
            );

            $task->project_id = $project?->getKey();

            if ($task->isDirty('project_id')) {
                // Смена проекта сбрасывает связанные с ним этап и спринт.
                $task->stage_id = null;
                $task->sprint_id = null;
            }
        }

        if ($data->has('estimate_minutes')) {
            if ($data->estimateMinutes !== null && $data->estimateMinutes < 0) {
                $this->invalid('estimate_minutes', 'Оценка времени не может быть отрицательной.');
            }

            $task->estimate_minutes = $data->estimateMinutes;
        }

        $startsAt = $data->has('starts_at')
            ? $data->startsAt
            : $this->immutable($task->starts_at);
        $dueAt = $data->has('due_at')
            ? $data->dueAt
            : $this->immutable($task->due_at);

        if ($startsAt !== null && $dueAt !== null && $dueAt < $startsAt) {
            $this->invalid('due_at', 'Срок задачи не может быть раньше даты начала.');
        }

        if ($data->has('starts_at')) {
            $task->starts_at = $data->startsAt;
        }

        if ($data->has('due_at')) {
            $task->due_at = $data->dueAt;
        }

        $changed = array_keys($task->getDirty());

        if ($changed !== []) {
            $task->save();
            TaskUpdated::dispatch($task, $changed);
        }

        return TaskReference::fromTask($task);
    }

    private function validateProvidedFields(UpdateTaskData $data): void
    {
        if ($data->provided === []) {
            $this->invalid('task', 'Передайте хотя бы одно изменяемое поле задачи.');
        }

        $unknown = array_diff($data->provided, UpdateTaskData::fields());

        if ($unknown !== [] || count(array_unique($data->provided)) !== count($data->provided)) {
            $this->invalid('task', 'Набор изменяемых полей задачи недопустим.');
        }
    }

    private function project(?string $id, string $workspaceId, ?string $connectionName): ?Project
    {
        if ($id === null) {
            return null;
        }

        /** @var Project|null $project */
        $project = Project::on($connectionName)->find($id);

        if ($project === null || $project->workspace_id !== $workspaceId) {
            $this->invalid('project_id', 'Проект не найден в рабочем пространстве задачи.');
        }

        return $project;
    }

    private function immutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return DateTimeImmutable::createFromInterface($value);
    }

    /** @return never */
    private function invalid(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
