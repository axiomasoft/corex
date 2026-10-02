<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Data\TaskOperationKind;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\TriggerRequest;
use Illuminate\Database\Connection;

final class CaptureTaskCompletion implements TaskCompletionCapture
{
    public function __construct(
        private readonly Connection $connection,
        private readonly bool $enabled,
        private readonly string $definitionRef,
        private readonly string $versionRef,
        private readonly TaskOperationReceipts $receipts,
    ) {}

    public function capture(TaskCompletionCandidate $candidate): void
    {
        if (! $this->enabled) {
            return;
        }

        if ($candidate->status !== TaskStatus::Done || ! $candidate->statusChanged) {
            return;
        }

        if (! interface_exists(WorkflowBinding::class)
            || ! app()->bound(WorkflowBinding::class)) {
            return;
        }

        $workflowBinding = app()->make(WorkflowBinding::class);
        $restoreEpoch = str_repeat('0', 64);
        $requestKey = hash('sha256', 'completion-capture/1'."\0".$candidate->taskId."\0".$restoreEpoch);
        $payload = [
            'taskId' => $candidate->taskId,
            'status' => TaskStatus::Done->value,
        ];
        $payloadHash = hash('sha256', 'completionCapture/1'."\0".$requestKey."\0".json_encode($payload, JSON_THROW_ON_ERROR));

        $existing = $this->receipts->find($this->connection, $requestKey);

        if ($existing !== null) {
            return;
        }

        $workflowBinding->capture(new TriggerRequest(
            context: app(TenantContextResolver::class)->current(),
            module: 'axiomasoft/corex-tasks-workflow',
            entityHandle: 'tsk.task',
            eventId: 'task:'.$candidate->taskId,
            eventName: 'completed',
            subjectRef: 'task:'.$candidate->taskId,
            definitionRef: $this->definitionRef,
            versionRef: $this->versionRef,
            scalarSnapshot: JsonDocuments::capture($payload),
            requestKey: $requestKey,
        ));

        $this->receipts->record(
            connection: $this->connection,
            kind: TaskOperationKind::CompletionCapture,
            requestKey: $requestKey,
            payloadHash: $payloadHash,
            restoreEpoch: $restoreEpoch,
            resultRef: ['taskId' => $candidate->taskId],
        );
    }
}
