<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal\Actions;

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskModuleContext as TaskModuleGuard;
use CoreX\Tasks\Project\Contracts\ProjectRead;
use CoreX\Tasks\Project\Data\ProjectView;
use CoreX\Tasks\Sprint\Contracts\SprintRead;
use CoreX\Tasks\Sprint\Data\SprintView;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Events\TaskCreated;
use CoreX\Tasks\Task\Internal\Models\Stage;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\Task\Internal\Models\TaskTarget;
use CoreX\Tasks\Task\Internal\TaskReferenceFactory;
use CoreX\Tasks\Task\Internal\TaskSnapshot;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateTask implements CreateTaskCommand
{
    public function __construct(
        private readonly TaskModuleGuard $moduleGuard,
        private readonly TaskAccess $access,
        private readonly ProjectRead $projects,
        private readonly SprintRead $sprints,
        private readonly Dispatcher $events,
    ) {}

    public function handle(TaskDraft $draft): TaskReference
    {
        $context = $this->moduleGuard->context();
        $this->access->authorizeCreate(draft: $draft, context: $context);

        if ($context->workspace !== null && $context->workspace->id !== $draft->workspaceId) {
            $this->invalid('workspace_id', 'Задачу нельзя создать в другом рабочем пространстве.');
        }

        $connection = (new Task)->getConnection();

        return $connection->transaction(
            callback: fn (): TaskReference => $this->create(
                draft: $draft,
                context: $context,
                connection: $connection,
            ),
        );
    }

    private function create(TaskDraft $draft, TenantContext $context, Connection $connection): TaskReference
    {
        $connectionName = $connection->getName();
        $title = trim($draft->title);

        if ($title === '' || mb_strlen($title) > 255) {
            $this->invalid('title', 'Название задачи должно содержать от 1 до 255 символов.');
        }

        if (! Str::isUuid($draft->workspaceId) || ! $connection->table('wsp_workspaces')->where('id', $draft->workspaceId)->exists()) {
            $this->invalid('workspace_id', 'Рабочее пространство не найдено в текущем аккаунте.');
        }

        if ($draft->estimateMinutes !== null && $draft->estimateMinutes < 0) {
            $this->invalid('estimate_minutes', 'Оценка времени не может быть отрицательной.');
        }

        if ($draft->startsAt !== null && $draft->dueAt !== null && $draft->dueAt < $draft->startsAt) {
            $this->invalid('due_at', 'Срок задачи не может быть раньше даты начала.');
        }

        $authorId = $draft->authorId;

        if ($authorId === null && $draft->actor->type === ActorType::User) {
            $authorId = $draft->actor->id;
        }

        foreach (['author_id' => $authorId, 'assignee_id' => $draft->assigneeId] as $field => $userId) {
            if ($userId !== null && (! Str::isUuid($userId) || ! $connection->table('users')->where('id', $userId)->exists())) {
                $this->invalid($field, 'Пользователь не найден в текущем аккаунте.');
            }
        }

        $project = $this->project(
            id: $draft->projectId,
            workspaceId: $draft->workspaceId,
            connectionName: $connectionName,
        );
        $this->validateSprint(id: $draft->sprintId, project: $project, connectionName: $connectionName);
        $this->validateStage(id: $draft->stageId, project: $project, connectionName: $connectionName);
        $this->validateParent(id: $draft->parentId, workspaceId: $draft->workspaceId, connectionName: $connectionName);

        $seenTargets = [];

        foreach ($draft->targets as $target) {
            if ($target->type === '' || mb_strlen($target->type) > 64 || ! Str::isUuid($target->id)) {
                $this->invalid('targets', 'Связанная сущность должна иметь непустой тип до 64 символов и UUID.');
            }

            $key = $target->type.':'.$target->id;

            if (isset($seenTargets[$key])) {
                $this->invalid('targets', 'Связанные сущности задачи не должны повторяться.');
            }

            $seenTargets[$key] = true;
            $this->access->authorizeTarget(target: $target, actor: $draft->actor, context: $context);
        }

        $task = new Task([
            'workspace_id' => $draft->workspaceId,
            'project_id' => $draft->projectId,
            'sprint_id' => $draft->sprintId,
            'stage_id' => $draft->stageId,
            'parent_id' => $draft->parentId,
            'title' => $title,
            'description' => $draft->description,
            'priority' => $draft->priority,
            'author_id' => $authorId,
            'assignee_id' => $draft->assigneeId,
            'created_by' => JsonDocuments::decodeTree($draft->actor->toArray()),
            'starts_at' => $draft->startsAt,
            'due_at' => $draft->dueAt,
            'estimate_minutes' => $draft->estimateMinutes,
        ]);
        $task->setConnection($connectionName);
        $task->save();

        foreach ($draft->targets as $target) {
            $link = new TaskTarget([
                'task_id' => $task->getKey(),
                'entity_type' => $target->type,
                'entity_id' => $target->id,
            ]);
            $link->setConnection($connectionName);
            $link->save();
        }

        $this->events->dispatch(new TaskCreated(TaskSnapshot::fromTask($task)));

        return TaskReferenceFactory::fromTask($task);
    }

    private function project(?string $id, string $workspaceId, ?string $connectionName): ?ProjectView
    {
        if ($id === null) {
            return null;
        }

        /** @var ProjectView|null $project */
        $project = $this->projects->find($id, $connectionName);

        if ($project === null || $project->workspaceId !== $workspaceId) {
            $this->invalid('project_id', 'Проект не найден в рабочем пространстве задачи.');
        }

        return $project;
    }

    private function validateSprint(?string $id, ?ProjectView $project, ?string $connectionName): void
    {
        if ($id === null) {
            return;
        }

        /** @var SprintView|null $sprint */
        $sprint = $this->sprints->find($id, $connectionName);

        if ($project === null || $sprint === null || $sprint->projectId !== $project->id) {
            $this->invalid('sprint_id', 'Спринт не принадлежит проекту задачи.');
        }
    }

    private function validateStage(?string $id, ?ProjectView $project, ?string $connectionName): void
    {
        if ($id === null) {
            return;
        }

        /** @var Stage|null $stage */
        $stage = Stage::on($connectionName)->find($id);

        if ($project === null || $stage === null || $stage->project_id !== $project->id) {
            $this->invalid('stage_id', 'Стадия не принадлежит проекту задачи.');
        }
    }

    private function validateParent(?string $id, string $workspaceId, ?string $connectionName): void
    {
        if ($id === null) {
            return;
        }

        /** @var Task|null $parent */
        $parent = Task::on($connectionName)->find($id);

        if ($parent === null || $parent->workspace_id !== $workspaceId) {
            $this->invalid('parent_id', 'Родительская задача не найдена в рабочем пространстве.');
        }

        if ($parent->parent_id !== null) {
            $this->invalid('parent_id', 'Поддерживается только один уровень подзадач.');
        }
    }

    /** @return never */
    private function invalid(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
