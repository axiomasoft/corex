<?php

declare(strict_types=1);

namespace CoreX\Tasks\Internal\Planning;

use Closure;
use CoreX\Tasks\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Contracts\TaskPlanningCommand;
use CoreX\Tasks\Data\PlanningOperation;
use CoreX\Tasks\Data\PlanningSubject;
use CoreX\Tasks\Data\PlanningToken;
use CoreX\Tasks\Data\ProjectDraft;
use CoreX\Tasks\Data\ProjectReference;
use CoreX\Tasks\Data\SprintDraft;
use CoreX\Tasks\Data\SprintReference;
use CoreX\Tasks\Data\TimeLogDraft;
use CoreX\Tasks\Data\TimeLogReference;
use CoreX\Tasks\Enums\ProjectStatus;
use CoreX\Tasks\Enums\SprintStatus;
use CoreX\Tasks\Exceptions\InvalidPlanningDraft;
use CoreX\Tasks\Models\Project;
use CoreX\Tasks\Models\Sprint;
use CoreX\Tasks\Models\Task;
use CoreX\Tasks\Models\TimeLog;
use CoreX\Tasks\Support\TaskModuleGuard;
use CoreX\Tenancy\TenantContext;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlanningCommand implements TaskPlanningCommand
{
    private const int MAX_DEADLOCK_RETRIES = 3;

    public function __construct(
        private readonly TaskModuleGuard $moduleGuard,
        private readonly TaskPlanningAccess $access,
    ) {}

    public function createProject(ProjectDraft $draft, PlanningToken $token): ProjectReference
    {
        $context = $this->moduleGuard->context();
        $name = $this->validatedName($draft->name, 'name');
        $this->assertToken($token, PlanningHasher::project($draft, $context));
        $this->assertWorkspace($draft->workspaceId, $context);

        $subject = new PlanningSubject(
            workspaceId: $draft->workspaceId,
            projectId: null,
            taskId: null,
            userId: $draft->actor->id,
        );
        $this->access->authorize(
            operation: PlanningOperation::CreateProject,
            subject: $subject,
            actor: $draft->actor,
            context: $context,
        );

        $connection = (new Project)->getConnection();

        return $connection->transaction(
            callback: function () use ($draft, $token, $context, $name, $subject, $connection): ProjectReference {
                $store = new OperationStore($connection);
                $restoreEpoch = PlanningHasher::restoreEpoch($context);
                $replay = $store->claimOrReplay(
                    operation: PlanningOperation::CreateProject,
                    token: $token,
                    restoreEpoch: $restoreEpoch,
                );

                if ($replay !== null) {
                    $this->access->authorize(
                        operation: PlanningOperation::CreateProject,
                        subject: $subject,
                        actor: $draft->actor,
                        context: $context,
                    );

                    return ProjectReference::fromStored($replay);
                }

                if (! Str::isUuid($draft->workspaceId) || ! $connection->table('wsp_workspaces')->where('id', $draft->workspaceId)->exists()) {
                    $this->invalid('workspace_id', 'Рабочее пространство не найдено в текущем аккаунте.');
                }

                $project = new Project([
                    'workspace_id' => $draft->workspaceId,
                    'name' => $name,
                    'status' => ProjectStatus::Active,
                    'created_by' => $draft->actor->toArray(),
                ]);
                $project->setConnection($connection->getName());
                $project->save();

                $reference = ProjectReference::fromProject($project);
                $store->claimOrReplay(
                    operation: PlanningOperation::CreateProject,
                    token: $token,
                    restoreEpoch: $restoreEpoch,
                    resultRef: $reference->toArray(),
                );

                return $reference;
            },
        );
    }

    public function createSprint(SprintDraft $draft, PlanningToken $token): SprintReference
    {
        $context = $this->moduleGuard->context();
        $name = $this->validatedName($draft->name, 'name');
        [$startDate, $endDate] = $this->validatedSprintDates($draft->startDate, $draft->endDate);
        $this->assertToken($token, PlanningHasher::sprint($draft, $context, $startDate, $endDate));

        $connection = (new Sprint)->getConnection();
        $project = $this->resolvedProject($draft->projectId, $connection);

        $subject = new PlanningSubject(
            workspaceId: (string) $project->workspace_id,
            projectId: (string) $project->getKey(),
            taskId: null,
            userId: $draft->actor->id,
        );
        $this->access->authorize(
            operation: PlanningOperation::CreateSprint,
            subject: $subject,
            actor: $draft->actor,
            context: $context,
        );

        return $connection->transaction(
            callback: function () use ($draft, $token, $context, $name, $startDate, $endDate, $subject, $connection, $project): SprintReference {
                $store = new OperationStore($connection);
                $restoreEpoch = PlanningHasher::restoreEpoch($context);
                $replay = $store->claimOrReplay(
                    operation: PlanningOperation::CreateSprint,
                    token: $token,
                    restoreEpoch: $restoreEpoch,
                );

                if ($replay !== null) {
                    $this->access->authorize(
                        operation: PlanningOperation::CreateSprint,
                        subject: $subject,
                        actor: $draft->actor,
                        context: $context,
                    );

                    return SprintReference::fromStored($replay);
                }

                $sprint = new Sprint([
                    'project_id' => $project->getKey(),
                    'name' => $name,
                    'status' => SprintStatus::Planned,
                    'starts_on' => $startDate,
                    'ends_on' => $endDate,
                ]);
                $sprint->setConnection($connection->getName());
                $sprint->save();

                $reference = SprintReference::fromSprint($sprint);
                $store->claimOrReplay(
                    operation: PlanningOperation::CreateSprint,
                    token: $token,
                    restoreEpoch: $restoreEpoch,
                    resultRef: $reference->toArray(),
                );

                return $reference;
            },
        );
    }

    public function recordTime(TimeLogDraft $draft, PlanningToken $token): TimeLogReference
    {
        $context = $this->moduleGuard->context();
        $this->assertToken($token, PlanningHasher::timeLog($draft, $context));
        $connection = (new Task)->getConnection();
        $this->validatedTimeLog($draft, $connection);

        return $this->transactionWithRetry(
            connection: $connection,
            callback: function () use ($draft, $token, $context, $connection): TimeLogReference {
                $store = new OperationStore($connection);
                $restoreEpoch = PlanningHasher::restoreEpoch($context);
                $replay = $store->claimOrReplay(
                    operation: PlanningOperation::RecordTime,
                    token: $token,
                    restoreEpoch: $restoreEpoch,
                );

                if ($replay !== null) {
                    $task = $this->lockedTask($draft->taskId, $connection);
                    $subject = $this->timeLogSubject($task, $draft->userId);
                    $this->access->authorize(
                        operation: PlanningOperation::RecordTime,
                        subject: $subject,
                        actor: $draft->actor,
                        context: $context,
                    );

                    return TimeLogReference::fromStored($replay);
                }

                $task = $this->lockedTask($draft->taskId, $connection);
                $subject = $this->timeLogSubject($task, $draft->userId);
                $this->access->authorize(
                    operation: PlanningOperation::RecordTime,
                    subject: $subject,
                    actor: $draft->actor,
                    context: $context,
                );

                if ($task->project_id !== null) {
                    Project::on($connection->getName())
                        ->whereKey($task->project_id)
                        ->lockForUpdate()
                        ->first();
                }

                if ($task->sprint_id !== null) {
                    Sprint::on($connection->getName())
                        ->whereKey($task->sprint_id)
                        ->lockForUpdate()
                        ->first();
                }

                $nextTotal = $task->spent_minutes + $draft->minutes;

                if ($nextTotal > 2_147_483_647) {
                    throw InvalidPlanningDraft::forField('minutes', 'Суммарное время задачи превышает допустимый предел.');
                }

                $timeLog = new TimeLog([
                    'task_id' => $task->getKey(),
                    'user_id' => $draft->userId,
                    'minutes' => $draft->minutes,
                    'spent_on' => $draft->spentOn,
                    'comment' => $draft->comment,
                    'source' => $draft->source,
                ]);
                $timeLog->setConnection($connection->getName());
                $timeLog->save();

                $task->spent_minutes = $nextTotal;
                $task->save();

                $reference = TimeLogReference::fromTimeLog($timeLog);
                $store->claimOrReplay(
                    operation: PlanningOperation::RecordTime,
                    token: $token,
                    restoreEpoch: $restoreEpoch,
                    resultRef: $reference->toArray(),
                );

                return $reference;
            },
        );
    }

    private function assertWorkspace(string $workspaceId, TenantContext $context): void
    {
        if ($context->workspace !== null && $context->workspace->id !== $workspaceId) {
            $this->invalid('workspace_id', 'Проект нельзя создать в другом рабочем пространстве.');
        }
    }

    private function assertToken(PlanningToken $token, string $expectedHash): void
    {
        if ($token->requestKey === '' || strlen($token->requestKey) > 190) {
            throw InvalidPlanningDraft::forField('request_key', 'Ключ запроса должен содержать от 1 до 190 байт.');
        }

        if (! preg_match('/^[a-f0-9]{64}$/', $token->payloadHash)) {
            throw InvalidPlanningDraft::forField('payload_hash', 'Хеш запроса должен быть SHA-256 в нижнем регистре.');
        }

        if (! hash_equals($expectedHash, $token->payloadHash)) {
            throw InvalidPlanningDraft::forField('payload_hash', 'Хеш запроса не совпадает с каноническим представлением.');
        }
    }

    private function validatedName(string $name, string $field): string
    {
        $trimmed = trim($name);

        if ($trimmed === '' || mb_strlen($trimmed) > 190) {
            throw InvalidPlanningDraft::forField($field, 'Название должно содержать от 1 до 190 символов.');
        }

        return $trimmed;
    }

    /** @return array{0: string, 1: string} */
    private function validatedSprintDates(?string $startDate, ?string $endDate): array
    {
        $start = $startDate ?? (new DateTimeImmutable('today'))->format('Y-m-d');
        $end = $endDate ?? (new DateTimeImmutable($start))->modify('+13 days')->format('Y-m-d');

        foreach (['startDate' => $start, 'endDate' => $end] as $field => $value) {
            if (! $this->isValidDate($value)) {
                throw InvalidPlanningDraft::forField($field, 'Дата должна быть в формате YYYY-MM-DD.');
            }
        }

        if ($end < $start) {
            throw InvalidPlanningDraft::forField('endDate', 'Дата окончания спринта не может быть раньше даты начала.');
        }

        return [$start, $end];
    }

    private function validatedTimeLog(TimeLogDraft $draft, Connection $connection): void
    {
        if ($draft->minutes < 1 || $draft->minutes > 2_147_483_647) {
            throw InvalidPlanningDraft::forField('minutes', 'Минуты должны быть положительным целым числом.');
        }

        if (! $this->isValidDate($draft->spentOn)) {
            throw InvalidPlanningDraft::forField('spentOn', 'Дата должна быть в формате YYYY-MM-DD.');
        }

        if ($draft->comment !== null && mb_strlen($draft->comment) > 2000) {
            throw InvalidPlanningDraft::forField('comment', 'Комментарий не может превышать 2000 символов.');
        }

        if (! Str::isUuid($draft->userId) || ! $connection->table('users')->where('id', $draft->userId)->exists()) {
            throw InvalidPlanningDraft::forField('user_id', 'Пользователь не найден в текущем аккаунте.');
        }
    }

    private function resolvedProject(string $projectId, Connection $connection): Project
    {
        if (! Str::isUuid($projectId)) {
            throw InvalidPlanningDraft::forField('project_id', 'Проект не найден в текущем аккаунте.');
        }

        /** @var Project|null $project */
        $project = Project::on($connection->getName())->find($projectId);

        if ($project === null) {
            throw InvalidPlanningDraft::forField('project_id', 'Проект не найден в текущем аккаунте.');
        }

        return $project;
    }

    private function lockedTask(string $taskId, Connection $connection): Task
    {
        if (! Str::isUuid($taskId)) {
            $this->invalid('task_id', 'Задача не найдена в текущем аккаунте.');
        }

        /** @var Task|null $task */
        $task = Task::on($connection->getName())
            ->whereKey($taskId)
            ->lockForUpdate()
            ->first();

        if ($task === null) {
            $this->invalid('task_id', 'Задача не найдена в текущем аккаунте.');
        }

        return $task;
    }

    private function timeLogSubject(Task $task, string $userId): PlanningSubject
    {
        return new PlanningSubject(
            workspaceId: (string) ($task->workspace_id ?? ''),
            projectId: $task->project_id,
            taskId: (string) $task->getKey(),
            userId: $userId,
        );
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @param  Closure(): TimeLogReference  $callback */
    private function transactionWithRetry(Connection $connection, Closure $callback): TimeLogReference
    {
        $attempt = 0;

        while (true) {
            try {
                return $connection->transaction($callback);
            } catch (QueryException $exception) {
                $attempt++;

                if ($attempt >= self::MAX_DEADLOCK_RETRIES || ! $this->isDeadlock($exception)) {
                    throw $exception;
                }
            }
        }
    }

    private function isDeadlock(QueryException $exception): bool
    {
        $code = $exception->errorInfo[0] ?? '';

        return in_array($code, ['40001', '40P01'], true);
    }

    /** @return never */
    private function invalid(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
