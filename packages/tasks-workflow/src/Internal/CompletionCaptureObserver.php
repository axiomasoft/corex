<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Tasks\Enums\TaskStatus;
use CoreX\Tasks\Models\Task;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\TriggerRequest;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

final class CompletionCaptureObserver
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Repository $config,
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

        $existing = $this->connection->table('corex_task_operations')->where('request_key', $requestKey)->first();

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
            definitionRef: 'task-completion/1',
            versionRef: '1',
            scalarSnapshot: $payload,
            requestKey: $requestKey,
        ));

        $this->connection->table('corex_task_operations')->insert([
            'id' => (string) Str::uuid7(),
            'request_key' => $requestKey,
            'payload_hash' => $payloadHash,
            'kind' => 'completionCapture',
            'result_ref' => json_encode(['taskId' => (string) $task->getKey()], JSON_THROW_ON_ERROR),
            'restore_epoch' => $restoreEpoch,
            'created_at' => now(),
        ]);
    }
}
