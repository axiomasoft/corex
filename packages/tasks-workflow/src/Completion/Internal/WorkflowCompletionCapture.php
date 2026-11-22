<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Completion\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Data\TaskCompletionAdmission;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use CoreX\Tasks\Task\Enums\TaskCompletionAdmissionStatus;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\CaptureStatus;
use Illuminate\Database\ConnectionInterface;
use LogicException;
use Override;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

#[Internal]
final readonly class WorkflowCompletionCapture implements TaskCompletionCapture
{
    public function __construct(
        private CurrentWorkScope $scopes,
        private ModuleRegistry $modules,
        private WorkflowBinding $workflow,
        private string $definitionRef,
        private string $versionRef,
    ) {}

    #[Override]
    public function capture(TaskCompletionCandidate $candidate, ConnectionInterface $connection): TaskCompletionAdmission
    {
        if ($connection->transactionLevel() < 1) {
            throw new LogicException('Task workflow completion requires the active Task owner transaction.');
        }

        $context = $this->scopes->assert(scope: $candidate->scope, connection: $connection);

        if (! $this->modules->isActive(TaskCompletionTrigger::MODULE, $context)) {
            throw new LogicException('Task workflow completion requires the active Tasks module.');
        }

        TaskCompletionTrigger::assertDeclared($this->modules);

        $taskRef = 'task:'.$candidate->taskId;
        $result = $this->workflow->capture(request: new TriggerRequest(
            context: $context,
            scope: $candidate->scope,
            module: TaskCompletionTrigger::MODULE,
            entityHandle: TaskCompletionTrigger::ENTITY,
            eventId: $taskRef,
            eventName: TaskCompletionTrigger::EVENT,
            subjectRef: $taskRef,
            definitionRef: $this->definitionRef,
            versionRef: $this->versionRef,
            scalarSnapshot: JsonDocuments::capture(['taskId' => $candidate->taskId, 'status' => $candidate->status->value]),
            requestKey: $candidate->requestKey,
        ), connection: $connection);

        return match ($result->status) {
            CaptureStatus::Created => new TaskCompletionAdmission(status: TaskCompletionAdmissionStatus::Created, intentRef: $result->intentRef, digest: $result->digest, versionRef: $this->versionRef),
            CaptureStatus::Existing => new TaskCompletionAdmission(status: TaskCompletionAdmissionStatus::Existing, intentRef: $result->intentRef, digest: $result->digest, versionRef: $this->versionRef),
            CaptureStatus::Conflict => new TaskCompletionAdmission(TaskCompletionAdmissionStatus::Conflict),
        };
    }
}
