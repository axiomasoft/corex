<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Data\TaskOperationFence;
use CoreX\Tasks\Planning\Data\TaskOperationIdentity;
use CoreX\Tasks\Planning\Data\TaskOperationKind;
use CoreX\Tasks\Planning\Data\TaskOperationReceipt;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use CoreX\Tasks\Task\Enums\TaskCompletionAdmissionStatus;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Tasks\Task\Internal\Models\Task;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class TaskCompletion
{
    public function __construct(
        private TaskMutationAccess $access,
        private ClockInterface $clock,
        private DataScopeFactory $scopes,
        private CurrentWorkScope $workScope,
        private TaskOperationReceipts $receipts,
        private ?TaskCompletionCapture $capture,
    ) {}

    public function scope(TenantContext $context, ConnectionInterface $connection): DataScope
    {
        $scope = $this->scopes->forCurrentPrincipal($context);
        $this->workScope->assert($scope, $connection);

        return $scope;
    }

    public function lockTask(string $taskId, DataScope $scope, Connection $connection): Task
    {
        /** @var Task|null $task */
        $task = Str::isUuid($taskId) ? Task::on($connection->getName())
            ->tap(static fn ($query) => $query->getQuery()->lockForUpdate())
            ->find($taskId) : null;

        if ($task === null) {
            throw ValidationException::withMessages(['task_id' => ['Задача не найдена в текущем аккаунте.']]);
        }

        if ($scope->workspaceId !== null && $scope->workspaceId !== $task->workspace_id) {
            throw ValidationException::withMessages(['task_id' => ['Задача не принадлежит текущему рабочему пространству.']]);
        }

        return $task;
    }

    /** Apply to the locked Task. The caller persists it and dispatches after commit. */
    public function apply(Task $task, CompleteTaskData $data, TenantContext $context, DataScope $scope, ConnectionInterface $connection): bool
    {
        $this->access->authorizeComplete(data: $data, context: $context, task: TaskSnapshot::fromTask($task));

        if ($task->getConnection() !== $connection || $connection->transactionLevel() < 1) {
            throw new LogicException('Task completion requires the locked Task owner transaction.');
        }
        $this->workScope->assert($scope, $connection);

        if ($task->status === TaskStatus::Done) {
            return false;
        }

        if ($task->status === TaskStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => ['Отменённую задачу нельзя завершить.']]);
        }

        $this->admit($task, $data, $context, $scope, $connection);
        $task->status = TaskStatus::Done;
        $task->completed_at = $this->clock->now();

        return true;
    }

    private function admit(Task $task, CompleteTaskData $data, TenantContext $context, DataScope $scope, ConnectionInterface $connection): void
    {
        $requestKey = hash('sha256', json_encode([
            'completion-capture/2', $scope->accountId, $scope->accountIncarnation, $task->id,
        ], JSON_THROW_ON_ERROR));
        $identity = new TaskOperationIdentity(
            scope: $scope,
            kind: TaskOperationKind::CompletionCapture,
            requestKey: $requestKey,
            subjectRef: 'task:'.$task->id,
        );

        $prior = $this->receipts->find($connection, $identity);

        if ($prior !== null) {
            $ref = JsonDocuments::map($prior->resultRef);

            if (($ref['taskId'] ?? null) !== $task->id || ! is_string($ref['intentRef'] ?? null) || $ref['intentRef'] === ''
                || ! is_string($ref['versionRef'] ?? null) || $ref['versionRef'] === '') {
                throw new LogicException('Stored task completion receipt has invalid intent evidence.');
            }

            return;
        }

        if ($this->capture === null) {
            return;
        }

        $admission = $this->capture->capture(new TaskCompletionCandidate(
            context: $context,
            scope: $scope,
            taskId: $task->id,
            previousStatus: $task->status,
            status: TaskStatus::Done,
            requestKey: $requestKey,
            actor: $data->actor,
        ), $connection);

        if ($admission->status === TaskCompletionAdmissionStatus::Conflict || $admission->digest === null
            || $admission->intentRef === null || $admission->versionRef === null) {
            throw new LogicException('Conflicting completion intent for the task.');
        }

        $this->receipts->record($connection, $identity, new TaskOperationReceipt(
            payloadHash: $admission->digest,
            originalFence: TaskOperationFence::fromScope($scope),
            resultRef: JsonDocuments::capture([
                'taskId' => $task->id,
                'intentRef' => $admission->intentRef,
                'versionRef' => $admission->versionRef,
            ]),
        ));
    }
}
