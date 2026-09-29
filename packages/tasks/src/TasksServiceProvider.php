<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Access\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Access\Internal\DenyTaskAccess;
use CoreX\Tasks\Access\Internal\DenyTaskMutationAccess;
use CoreX\Tasks\Access\Internal\DenyTaskPlanningAccess;
use CoreX\Tasks\Planning\Contracts\TaskPlanningCommand;
use CoreX\Tasks\Planning\Internal\PlanningCommand;
use CoreX\Tasks\Planning\Internal\PlanningOperationsServiceProvider;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Task\Contracts\UpdateTaskCommand;
use CoreX\Tasks\Task\Internal\Actions\CompleteTask;
use CoreX\Tasks\Task\Internal\Actions\CreateTask;
use CoreX\Tasks\Task\Internal\Actions\MoveTaskToStage;
use CoreX\Tasks\Task\Internal\Actions\UpdateTask;
use Illuminate\Support\ServiceProvider;

final class TasksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
}
