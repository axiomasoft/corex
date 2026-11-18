<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

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
use Packman\Attributes\Internal;

#[Internal]
final readonly class CaptureTaskCompletion implements TaskCompletionCapture
{
    public function __construct(
        private Connection $connection,
        private bool $enabled,
        private string $definitionRef,
        private string $versionRef,
        private TaskOperationReceipts $receipts,
        private TenantContextResolver $contexts,
        private ?WorkflowBinding $workflow = null,
    ) {}

    public function capture(TaskCompletionCandidate $candidate): void
    {
        if (! $this->enabled) {
            return;
        }

        if ($candidate->status !== TaskStatus::Done || ! $candidate->statusChanged) {
            return;
        }

        if ($this->workflow === null) {
            return;
        }

        $restoreEpoch = str_repeat('0', 64);
        $requestKey = hash('sha256', 'completion-capture/1'."\0".$candidate->taskId."\0".$restoreEpoch);
        $payload = [
            'taskId' => $candidate->taskId,
            'status' => TaskStatus::Done->value,
        ];
        $payloadHash = hash('sha256', 'completionCapture/1'."\0".$requestKey."\0".json_encode($payload, JSON_THROW_ON_ERROR));

        $existing = $this->receipts->find(connection: $this->connection, requestKey: $requestKey);

        if ($existing !== null) {
            return;
        }

        $this->workflow->capture(new TriggerRequest(
            context: $this->contexts->current(),
            module: 'axiomasoft/corex-recurring-tasks',
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
