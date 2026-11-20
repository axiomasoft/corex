<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Data\TaskOperationKind;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\CaptureStatus;
use Illuminate\Database\Connection;
use LogicException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CaptureTaskCompletion implements TaskCompletionCapture
{
    public function __construct(
        private bool $enabled,
        private string $definitionRef,
        private string $versionRef,
        private TaskOperationReceipts $receipts,
        private TenantContextResolver $contexts,
        private DataScopeFactory $scopes,
        private ?WorkflowBinding $workflow = null,
    ) {}

    public function capture(TaskCompletionCandidate $candidate, Connection $connection): void
    {
        if (! $this->enabled) {
            return;
        }

        if ($candidate->status !== TaskStatus::Done || ! $candidate->statusChanged) {
            return;
        }

        if ($this->workflow === null || $connection->transactionLevel() < 1) {
            throw new LogicException('Enabled workflow completion capture requires a workflow binding and the active Task owner transaction.');
        }

        $context = $this->contexts->current();
        $scope = $this->scopes->forCurrentPrincipal($context);
        $requestKey = hash('sha256', json_encode([
            'completion-capture/2', $scope->accountId, $scope->accountIncarnation, $candidate->taskId,
        ], JSON_THROW_ON_ERROR));
        $payload = [
            'taskId' => $candidate->taskId,
            'status' => TaskStatus::Done->value,
        ];
        $existing = $this->receipts->find(connection: $connection, requestKey: $requestKey);

        if ($existing !== null) {
            return;
        }

        $result = $this->workflow->capture(new TriggerRequest(
            context: $context,
            scope: $scope,
            module: 'axiomasoft/corex-recurring-tasks',
            entityHandle: 'tsk.task',
            eventId: 'task:'.$candidate->taskId,
            eventName: 'completed',
            subjectRef: 'task:'.$candidate->taskId,
            definitionRef: $this->definitionRef,
            versionRef: $this->versionRef,
            scalarSnapshot: JsonDocuments::capture($payload),
            requestKey: $requestKey,
        ), $connection);

        if ($result->status === CaptureStatus::Conflict || $result->intentRef === null || $result->digest === null) {
            throw new LogicException('Conflicting workflow completion intent for the task.');
        }

        $this->receipts->record(
            connection: $connection,
            kind: TaskOperationKind::CompletionCapture,
            requestKey: $requestKey,
            payloadHash: $result->digest,
            restoreEpoch: $scope->restoreEpoch,
            resultRef: ['taskId' => $candidate->taskId, 'intentRef' => $result->intentRef],
        );
    }
}
