<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Tasks\Actions\CompleteTask;
use CoreX\Tasks\Actions\CreateTask;
use CoreX\Tasks\Actions\MoveTaskToStage;
use CoreX\Tasks\Actions\UpdateTask;
use CoreX\Tasks\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Contracts\CreateTaskCommand;
use CoreX\Tasks\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Contracts\TaskMutationAccess;
use CoreX\Tasks\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Contracts\TaskPlanningCommand;
use CoreX\Tasks\Contracts\UpdateTaskCommand;
use CoreX\Tasks\Internal\Planning\PlanningCommand;
use CoreX\Tasks\Support\DenyTaskAccess;
use CoreX\Tasks\Support\DenyTaskMutationAccess;
use CoreX\Tasks\Support\DenyTaskPlanningAccess;
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
    }
}
