<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Access\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Access\Internal\DenyTaskAccess;
use CoreX\Tasks\Access\Internal\DenyTaskMutationAccess;
use CoreX\Tasks\Access\Internal\DenyTaskPlanningAccess;
use CoreX\Tasks\Errors\Internal\PackageProblemDefinitions;
use CoreX\Tasks\Internal\TasksSchemaSets;
use CoreX\Tasks\Planning\Contracts\TaskPlanningCommand;
use CoreX\Tasks\Planning\Internal\PlanningOperationsServiceProvider;
use CoreX\Tasks\Planning\Laravel\PlanningCommand;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Contracts\UpdateTaskCommand;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Tasks\Task\Internal\Actions\CompleteTask;
use CoreX\Tasks\Task\Internal\Actions\CreateTask;
use CoreX\Tasks\Task\Internal\Actions\MoveTaskToStage;
use CoreX\Tasks\Task\Internal\Actions\UpdateTask;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\Task\Laravel\TaskPortBindings;
use Illuminate\Support\ServiceProvider;
use LogicException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TasksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([TasksSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        TaskPortBindings::register($this->app);

        $this->app->bind(CreateTaskCommand::class, CreateTask::class);
        $this->app->bind(MoveTaskToStageCommand::class, MoveTaskToStage::class);
        $this->app->bind(UpdateTaskCommand::class, UpdateTask::class);
        $this->app->bind(CompleteTaskCommand::class, CompleteTask::class);
        $this->app->bind(TaskAccess::class, DenyTaskAccess::class);
        $this->app->bind(TaskMutationAccess::class, DenyTaskMutationAccess::class);
        $this->app->bind(TaskPlanningCommand::class, PlanningCommand::class);
        $this->app->bind(TaskPlanningAccess::class, DenyTaskPlanningAccess::class);
        $this->app->register(PlanningOperationsServiceProvider::class);
    }

    public function boot(): void
    {
        Task::saving($this->captureCompletion(...));
    }

    private function captureCompletion(Task $task): void
    {
        $raw = $task->getRawOriginal('status');
        $previous = is_string($raw) ? TaskStatus::from($raw) : TaskStatus::Open;
        $candidate = new TaskCompletionCandidate(
            taskId: ($task->id ?? ''),
            previousStatus: $previous,
            status: $task->status,
            statusChanged: $task->isDirty('status'),
        );

        foreach ($this->app->tagged('corex.tasks.completion-captures') as $capture) {
            if (! $capture instanceof TaskCompletionCapture) {
                throw new LogicException('Task completion captures must implement TaskCompletionCapture.');
            }

            $capture->capture($candidate);
        }
    }
}
