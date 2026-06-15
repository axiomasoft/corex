<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Contracts\EntityRegistry;
use CoreX\Tasks\Models\Process;
use CoreX\Tasks\Models\Project;
use CoreX\Tasks\Models\Stage;
use CoreX\Tasks\Models\Task;
use Illuminate\Support\ServiceProvider;

final class TasksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register entities so they enter corex/core's morph map (built in the
        // app.booted callback, after every module provider has booted).
        $registry = $this->app->make(EntityRegistry::class);
        $registry->register(Process::class, ['alias' => 'tsk_process']);
        $registry->register(Stage::class, ['alias' => 'tsk_stage']);
        $registry->register(Project::class, ['alias' => 'tsk_project']);
        $registry->register(Task::class, ['alias' => 'tsk_task', 'auditable' => true]);
    }
}
