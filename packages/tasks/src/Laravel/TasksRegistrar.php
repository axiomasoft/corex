<?php

declare(strict_types=1);

namespace CoreX\Tasks\Laravel;

use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Access\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Access\Internal\DenyTaskAccess;
use CoreX\Tasks\Access\Internal\DenyTaskMutationAccess;
use CoreX\Tasks\Access\Internal\DenyTaskPlanningAccess;
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
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TasksRegistrar implements PackageRegistrar
{
    public function register(Application $app): void
    {
        TaskPortBindings::register($app);

        $app->bind(CreateTaskCommand::class, CreateTask::class);
        $app->bind(MoveTaskToStageCommand::class, MoveTaskToStage::class);
        $app->bind(UpdateTaskCommand::class, UpdateTask::class);
        $app->bind(CompleteTaskCommand::class, CompleteTask::class);
        $app->bind(TaskAccess::class, DenyTaskAccess::class);
        $app->bind(TaskMutationAccess::class, DenyTaskMutationAccess::class);
        $app->bind(TaskPlanningCommand::class, PlanningCommand::class);
        $app->bind(TaskPlanningAccess::class, DenyTaskPlanningAccess::class);
        $app->register(PlanningOperationsServiceProvider::class);
    }

    public function boot(Application $app): void
    {
        Task::saving(fn (Task $task) => $this->captureCompletion($app, $task));
    }

    private function captureCompletion(Application $app, Task $task): void
    {
        $raw = $task->getRawOriginal('status');
        $previous = is_string($raw) ? TaskStatus::from($raw) : TaskStatus::Open;
        $candidate = new TaskCompletionCandidate(
            taskId: ($task->id ?? ''),
            previousStatus: $previous,
            status: $task->status,
            statusChanged: $task->isDirty('status'),
        );

        foreach ($app->tagged('corex.tasks.completion-captures') as $capture) {
            if (! $capture instanceof TaskCompletionCapture) {
                throw new LogicException('Task completion captures must implement TaskCompletionCapture.');
            }

            $capture->capture($candidate, $task->getConnection());
        }
    }
}
