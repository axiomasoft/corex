<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Actions;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Access\Contracts\TaskModuleContext as TaskModuleGuard;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Project\Contracts\ProjectRead;
use CoreX\Tasks\Project\Data\ProjectView;
use CoreX\Tasks\Task\Contracts\UpdateTaskCommand;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Data\UpdateTaskData;
use CoreX\Tasks\Task\Events\TaskUpdated;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\Task\Internal\TaskReferenceFactory;
use CoreX\Tasks\Task\Internal\TaskSnapshot;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class UpdateTask implements UpdateTaskCommand
{
    public function __construct(
        private TaskModuleGuard $moduleGuard,
        private TaskMutationAccess $access,
        private ProjectRead $projects,
        private Dispatcher $events,
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
        $task = Task::on($connectionName)->tap(static fn ($query) => $query->getQuery()->lockForUpdate())->find($data->taskId);

        if ($task === null) {
            $this->invalid(field: 'task_id', message: 'Задача не найдена в текущем аккаунте.');
        }

        if ($context->workspace !== null && $context->workspace->id !== $task->workspace_id) {
            $this->invalid(field: 'task_id', message: 'Задача не принадлежит текущему рабочему пространству.');
        }

        $this->access->authorizeUpdate(data: $data, context: $context, task: TaskSnapshot::fromTask($task));

        if ($data->has('title')) {
            $title = trim((string) $data->title);

            if ($title === '' || mb_strlen($title) > 255) {
                $this->invalid(field: 'title', message: 'Название задачи должно содержать от 1 до 255 символов.');
            }

            $task->title = $title;
        }

        if ($data->has('description')) {
            $task->description = $data->description;
        }

        if ($data->has('priority')) {
            if ($data->priority === null) {
                $this->invalid(field: 'priority', message: 'Приоритет задачи обязателен.');
            }

            $task->priority = $data->priority;
        }

        if ($data->has('assignee_id')) {
            if ($data->assigneeId !== null && (! Str::isUuid($data->assigneeId) || ! $connection->table('users')->where('id', $data->assigneeId)->exists())) {
                $this->invalid(field: 'assignee_id', message: 'Пользователь не найден в текущем аккаунте.');
            }

            $task->assignee_id = $data->assigneeId;
        }

        if ($data->has('project_id')) {
            $project = $this->project(
                id: $data->projectId,
                workspaceId: (string) $task->workspace_id,
                connectionName: $connectionName,
            );

            $task->project_id = $project?->id;

            if ($task->isDirty('project_id')) {
                // Смена проекта сбрасывает связанные с ним этап и спринт.
                $task->stage_id = null;
                $task->sprint_id = null;
            }
        }

        if ($data->has('estimate_minutes')) {
            if ($data->estimateMinutes !== null && $data->estimateMinutes < 0) {
                $this->invalid(field: 'estimate_minutes', message: 'Оценка времени не может быть отрицательной.');
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
            $this->invalid(field: 'due_at', message: 'Срок задачи не может быть раньше даты начала.');
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
            $this->events->dispatch(new TaskUpdated(task: TaskSnapshot::fromTask($task), changed: $changed));
        }

        return TaskReferenceFactory::fromTask($task);
    }

    private function validateProvidedFields(UpdateTaskData $data): void
    {
        if ($data->provided === []) {
            $this->invalid(field: 'task', message: 'Передайте хотя бы одно изменяемое поле задачи.');
        }

        $unknown = array_diff($data->provided, UpdateTaskData::fields());

        if ($unknown !== [] || count(array_unique($data->provided)) !== count($data->provided)) {
            $this->invalid(field: 'task', message: 'Набор изменяемых полей задачи недопустим.');
        }
    }

    private function project(?string $id, string $workspaceId, ?string $connectionName): ?ProjectView
    {
        if ($id === null) {
            return null;
        }

        /** @var ProjectView|null $project */
        $project = $this->projects->find(id: $id, connectionName: $connectionName);

        if ($project === null || $project->workspaceId !== $workspaceId) {
            $this->invalid(field: 'project_id', message: 'Проект не найден в рабочем пространстве задачи.');
        }

        return $project;
    }

    private function immutable(?DateTimeInterface $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return DateTimeImmutable::createFromInterface($value);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
