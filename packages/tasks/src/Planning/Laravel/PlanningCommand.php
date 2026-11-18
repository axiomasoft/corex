<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Laravel;

use Closure;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Access\Contracts\TaskModuleContext as TaskModuleGuard;
use CoreX\Tasks\Access\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Contracts\TaskPlanningCommand;
use CoreX\Tasks\Planning\Data\PlanningOperation;
use CoreX\Tasks\Planning\Data\PlanningSubject;
use CoreX\Tasks\Planning\Data\PlanningToken;
use CoreX\Tasks\Planning\Exceptions\InvalidPlanningDraft;
use CoreX\Tasks\Planning\Internal\OperationStore;
use CoreX\Tasks\Planning\Internal\PlanningHasher;
use CoreX\Tasks\Planning\Internal\Value;
use CoreX\Tasks\Project\Data\ProjectDraft;
use CoreX\Tasks\Project\Data\ProjectReference;
use CoreX\Tasks\Project\Enums\ProjectStatus;
use CoreX\Tasks\Project\Internal\Models\Project;
use CoreX\Tasks\Project\Internal\ProjectReferenceFactory;
use CoreX\Tasks\Sprint\Data\SprintDraft;
use CoreX\Tasks\Sprint\Data\SprintReference;
use CoreX\Tasks\Sprint\Enums\SprintStatus;
use CoreX\Tasks\Sprint\Internal\Models\Sprint;
use CoreX\Tasks\Sprint\Internal\SprintReferenceFactory;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\Time\Data\TimeLogDraft;
use CoreX\Tasks\Time\Data\TimeLogReference;
use CoreX\Tasks\Time\Internal\Models\TimeLog;
use CoreX\Tasks\Time\Internal\TimeLogReferenceFactory;
use DateTimeImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class PlanningCommand implements TaskPlanningCommand
{
    private const int MAX_DEADLOCK_RETRIES = 3;

    public function __construct(
        private TaskModuleGuard $moduleGuard,
        private TaskPlanningAccess $access,
        private ?TaskOperationReceipts $receipts = null,
    ) {}

    public function createProject(ProjectDraft $draft, PlanningToken $token): ProjectReference
    {
        $context = $this->moduleGuard->context();
        $name = $this->validatedName(name: $draft->name, field: 'name');
        $this->assertToken(token: $token, expectedHash: PlanningHasher::project(draft: $draft, context: $context));
        $this->assertWorkspace(workspaceId: $draft->workspaceId, context: $context);

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
                $store = new OperationStore(connection: $connection, receipts: $this->receipts());
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
                    $this->invalid(field: 'workspace_id', message: 'Рабочее пространство не найдено в текущем аккаунте.');
                }

                $project = new Project([
                    'workspace_id' => $draft->workspaceId,
                    'name' => $name,
                    'status' => ProjectStatus::Active,
                    'created_by' => JsonDocuments::decodeTree($draft->actor->toArray()),
                ]);
                $project->setConnection($connection->getName());
                $project->save();

                $reference = ProjectReferenceFactory::fromProject($project);
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
        $name = $this->validatedName(name: $draft->name, field: 'name');
        [$startDate, $endDate] = $this->validatedSprintDates(startDate: $draft->startDate, endDate: $draft->endDate);
        $this->assertToken(token: $token, expectedHash: PlanningHasher::sprint(draft: $draft, context: $context, startDate: $startDate, endDate: $endDate));

        $connection = (new Sprint)->getConnection();
        $project = $this->resolvedProject(projectId: $draft->projectId, connection: $connection);

        $subject = new PlanningSubject(
            workspaceId: (string) $project->workspace_id,
            projectId: Value::text($project->getKey()),
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
                $store = new OperationStore(connection: $connection, receipts: $this->receipts());
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

                $reference = SprintReferenceFactory::fromSprint($sprint);
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
        $this->assertToken(token: $token, expectedHash: PlanningHasher::timeLog(draft: $draft, context: $context));
        $connection = (new Task)->getConnection();
        $this->validatedTimeLog(draft: $draft, connection: $connection);

        return $this->transactionWithRetry(
            connection: $connection,
            callback: function () use ($draft, $token, $context, $connection): TimeLogReference {
                $store = new OperationStore(connection: $connection, receipts: $this->receipts());
                $restoreEpoch = PlanningHasher::restoreEpoch($context);
                $replay = $store->claimOrReplay(
                    operation: PlanningOperation::RecordTime,
                    token: $token,
                    restoreEpoch: $restoreEpoch,
                );

                if ($replay !== null) {
                    $task = $this->lockedTask(taskId: $draft->taskId, connection: $connection);
                    $subject = $this->timeLogSubject(task: $task, userId: $draft->userId);
                    $this->access->authorize(
                        operation: PlanningOperation::RecordTime,
                        subject: $subject,
                        actor: $draft->actor,
                        context: $context,
                    );

                    return TimeLogReference::fromStored($replay);
                }

                $task = $this->lockedTask(taskId: $draft->taskId, connection: $connection);
                $subject = $this->timeLogSubject(task: $task, userId: $draft->userId);
                $this->access->authorize(
                    operation: PlanningOperation::RecordTime,
                    subject: $subject,
                    actor: $draft->actor,
                    context: $context,
                );

                if ($task->project_id !== null) {
                    Project::on($connection->getName())
                        ->whereKey($task->project_id)
                        ->tap(static fn ($query) => $query->getQuery()->lockForUpdate())
                        ->first();
                }

                if ($task->sprint_id !== null) {
                    Sprint::on($connection->getName())
                        ->whereKey($task->sprint_id)
                        ->tap(static fn ($query) => $query->getQuery()->lockForUpdate())
                        ->first();
                }

                $nextTotal = $task->spent_minutes + $draft->minutes;

                if ($nextTotal > 2_147_483_647) {
                    throw InvalidPlanningDraft::forField(field: 'minutes', message: 'Суммарное время задачи превышает допустимый предел.');
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

                $reference = TimeLogReferenceFactory::fromTimeLog($timeLog);
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
            $this->invalid(field: 'workspace_id', message: 'Проект нельзя создать в другом рабочем пространстве.');
        }
    }

    private function assertToken(PlanningToken $token, string $expectedHash): void
    {
        if ($token->requestKey === '' || strlen($token->requestKey) > 190) {
            throw InvalidPlanningDraft::forField(field: 'request_key', message: 'Ключ запроса должен содержать от 1 до 190 байт.');
        }

        if (preg_match('/^[a-f0-9]{64}$/', $token->payloadHash) !== 1) {
            throw InvalidPlanningDraft::forField(field: 'payload_hash', message: 'Хеш запроса должен быть SHA-256 в нижнем регистре.');
        }

        if (! hash_equals($expectedHash, $token->payloadHash)) {
            throw InvalidPlanningDraft::forField(field: 'payload_hash', message: 'Хеш запроса не совпадает с каноническим представлением.');
        }
    }

    private function validatedName(string $name, string $field): string
    {
        $trimmed = trim($name);

        if ($trimmed === '' || mb_strlen($trimmed) > 190) {
            throw InvalidPlanningDraft::forField(field: $field, message: 'Название должно содержать от 1 до 190 символов.');
        }

        return $trimmed;
    }

    /** @return array{0: string, 1: string} */
    private function validatedSprintDates(?string $startDate, ?string $endDate): array
    {
        $start = $startDate ?? new DateTimeImmutable('today')->format('Y-m-d');
        $end = $endDate ?? new DateTimeImmutable($start)->modify('+13 days')->format('Y-m-d');

        foreach (['startDate' => $start, 'endDate' => $end] as $field => $value) {
            if (! $this->isValidDate($value)) {
                throw InvalidPlanningDraft::forField(field: $field, message: 'Дата должна быть в формате YYYY-MM-DD.');
            }
        }

        if ($end < $start) {
            throw InvalidPlanningDraft::forField(field: 'endDate', message: 'Дата окончания спринта не может быть раньше даты начала.');
        }

        return [$start, $end];
    }

    private function validatedTimeLog(TimeLogDraft $draft, Connection $connection): void
    {
        if ($draft->minutes < 1 || $draft->minutes > 2_147_483_647) {
            throw InvalidPlanningDraft::forField(field: 'minutes', message: 'Минуты должны быть положительным целым числом.');
        }

        if (! $this->isValidDate($draft->spentOn)) {
            throw InvalidPlanningDraft::forField(field: 'spentOn', message: 'Дата должна быть в формате YYYY-MM-DD.');
        }

        if ($draft->comment !== null && mb_strlen($draft->comment) > 2000) {
            throw InvalidPlanningDraft::forField(field: 'comment', message: 'Комментарий не может превышать 2000 символов.');
        }

        if (! Str::isUuid($draft->userId) || ! $connection->table('users')->where('id', $draft->userId)->exists()) {
            throw InvalidPlanningDraft::forField(field: 'user_id', message: 'Пользователь не найден в текущем аккаунте.');
        }
    }

    private function resolvedProject(string $projectId, Connection $connection): Project
    {
        if (! Str::isUuid($projectId)) {
            throw InvalidPlanningDraft::forField(field: 'project_id', message: 'Проект не найден в текущем аккаунте.');
        }

        /** @var Project|null $project */
        $project = Project::on($connection->getName())->find($projectId);

        if ($project === null) {
            throw InvalidPlanningDraft::forField(field: 'project_id', message: 'Проект не найден в текущем аккаунте.');
        }

        return $project;
    }

    private function lockedTask(string $taskId, Connection $connection): Task
    {
        if (! Str::isUuid($taskId)) {
            $this->invalid(field: 'task_id', message: 'Задача не найдена в текущем аккаунте.');
        }

        /** @var Task|null $task */
        $task = Task::on($connection->getName())
            ->whereKey($taskId)
            ->tap(static fn ($query) => $query->getQuery()->lockForUpdate())
            ->first();

        if ($task === null) {
            $this->invalid(field: 'task_id', message: 'Задача не найдена в текущем аккаунте.');
        }

        return $task;
    }

    private function timeLogSubject(Task $task, string $userId): PlanningSubject
    {
        return new PlanningSubject(
            workspaceId: ($task->workspace_id ?? ''),
            projectId: $task->project_id,
            taskId: Value::text($task->getKey()),
            userId: $userId,
        );
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @param Closure():TimeLogReference $callback */
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

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }

    /** The port is optional so the constructor stays source-compatible; the container bridge fills it when absent. */
    private function receipts(): TaskOperationReceipts
    {
        return $this->receipts ?? Container::getInstance()->make(TaskOperationReceipts::class);
    }
}
