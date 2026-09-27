<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Tasks\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Data\TaskOperationKind;
use CoreX\Tasks\Enums\TaskStatus;
use CoreX\Tasks\Models\Task;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\TriggerRequest;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;

final class CaptureTaskCompletion
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Repository $config,
        private readonly TaskOperationReceipts $receipts,
    ) {}

    public function saving(Task $task): void
    {
        if (! $this->config->get('corex-task-recurrence.completion_capture.enabled', false)) {
            return;
        }

        if ($task->status !== TaskStatus::Done || ! $task->isDirty('status')) {
            return;
        }

        if (! interface_exists(WorkflowBinding::class)
            || ! app()->bound(WorkflowBinding::class)) {
            return;
        }

        $workflowBinding = app()->make(WorkflowBinding::class);
        $restoreEpoch = str_repeat('0', 64);
        $requestKey = hash('sha256', 'completion-capture/1'."\0".$task->getKey()."\0".$restoreEpoch);
        $payload = [
            'taskId' => (string) $task->getKey(),
            'status' => TaskStatus::Done->value,
        ];
        $payloadHash = hash('sha256', 'completionCapture/1'."\0".$requestKey."\0".json_encode($payload, JSON_THROW_ON_ERROR));

        $existing = $this->receipts->find($this->connection, $requestKey);

        if ($existing !== null) {
            return;
        }

        $workflowBinding->capture(new TriggerRequest(
            context: app(TenantContextResolver::class)->current(),
            module: 'corex/tasks-workflow',
            entityHandle: 'tsk.task',
            eventId: 'task:'.$task->getKey(),
            eventName: 'completed',
            subjectRef: 'task:'.$task->getKey(),
            definitionRef: (string) $this->config->get('corex-task-recurrence.completion_capture.definition_ref', 'task-completion/1'),
            versionRef: (string) $this->config->get('corex-task-recurrence.completion_capture.version_ref', 'task-completion/1'),
            scalarSnapshot: $payload,
            requestKey: $requestKey,
        ));

        $this->receipts->record(
            connection: $this->connection,
            kind: TaskOperationKind::CompletionCapture,
            requestKey: $requestKey,
            payloadHash: $payloadHash,
            restoreEpoch: $restoreEpoch,
            resultRef: ['taskId' => (string) $task->getKey()],
        );
    }
}
