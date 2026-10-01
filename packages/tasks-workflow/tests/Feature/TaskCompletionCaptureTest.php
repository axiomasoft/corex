<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\CaptureResult;
use CoreX\Workflow\Data\TriggerRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->workspace = (string) Str::uuid7();
    $this->actorId = (string) Str::uuid7();
    DB::table('wsp_workspaces')->insert(['id' => $this->workspace, 'name' => 'Capture']);
    DB::table('users')->insert(['id' => $this->actorId, 'name' => 'Actor']);
    $this->actor = new ActorData(type: ActorType::User, id: $this->actorId);
    $this->taskId = app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspace,
        title: 'Atomic capture',
        actor: $this->actor,
    ))->id;
    config()->set('corex-tasks-workflow.completion_capture.enabled', true);
});

it('captures synchronously with a DTO and deduplicates the receipt on repeated completion', function (): void {
    $binding = Mockery::mock(WorkflowBinding::class);
    $binding->shouldReceive('capture')->once()->withArgs(function (TriggerRequest $request): bool {
        expect(DB::connection()->transactionLevel())->toBeGreaterThan(0)
            ->and(JsonDocuments::map($request->scalarSnapshot))->toBe(['taskId' => $this->taskId, 'status' => 'done'])
            ->and(Task::query()->findOrFail($this->taskId)->status)->toBe(TaskStatus::Open);

        return true;
    })->andReturn(new CaptureResult('captured'));
    app()->instance(WorkflowBinding::class, $binding);
    $data = new CompleteTaskData(taskId: $this->taskId, actor: $this->actor);
    app(CompleteTaskCommand::class)->handle($data);
    app(CompleteTaskCommand::class)->handle($data);
    $task = Task::query()->findOrFail($this->taskId);
    $task->title = 'Unchanged completion status';
    $task->save();
    // Re-enter Done from another status: the completion identity still has one receipt.
    $task->status = TaskStatus::Open;
    $task->save();
    app(CompleteTaskCommand::class)->handle($data);
    expect(DB::table('corex_task_operations')->count())->toBe(1);
});

it('rolls the task and already recorded receipt back when a later synchronous contributor throws', function (): void {
    $binding = Mockery::mock(WorkflowBinding::class);
    $binding->shouldReceive('capture')->once()->andReturn(new CaptureResult('captured'));
    app()->instance(WorkflowBinding::class, $binding);
    app()->instance('test.failing-capture', new class implements TaskCompletionCapture
    {
        public function capture(TaskCompletionCandidate $candidate): void
        {
            if ($candidate->status === TaskStatus::Done && $candidate->statusChanged) {
                throw new RuntimeException('Contributor failed');
            }
        }
    });
    app()->tag('test.failing-capture', 'corex.tasks.completion-captures');
    expect(fn () => app(CompleteTaskCommand::class)->handle(new CompleteTaskData(
        taskId: $this->taskId, actor: $this->actor,
    )))->toThrow(RuntimeException::class, 'Contributor failed');
    expect(Task::query()->findOrFail($this->taskId)->status)->toBe(TaskStatus::Open)
        ->and(DB::table('corex_task_operations')->count())->toBe(0);
});

it('preserves normal saves when capture is disabled or the workflow binding is missing', function (bool $disabled): void {
    if ($disabled) {
        config()->set('corex-tasks-workflow.completion_capture.enabled', false);
        $binding = Mockery::mock(WorkflowBinding::class);
        $binding->shouldNotReceive('capture');
        app()->instance(WorkflowBinding::class, $binding);
    }
    app(CompleteTaskCommand::class)->handle(new CompleteTaskData(taskId: $this->taskId, actor: $this->actor));
    expect(Task::query()->findOrFail($this->taskId)->status)->toBe(TaskStatus::Done)
        ->and(DB::table('corex_task_operations')->count())->toBe(0);
})->with([true, false]);
