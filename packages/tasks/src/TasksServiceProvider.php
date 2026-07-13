<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Tasks\Models\Process;
use CoreX\Tasks\Models\Project;
use CoreX\Tasks\Models\Stage;
use CoreX\Tasks\Models\Task;
use Flex\Modules\Enums\Capability;
use Flex\Modules\Registry\ArrayEntityRegistry;
use Flex\Modules\Registry\EntityDefinition;
use Illuminate\Support\ServiceProvider;

final class TasksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register entities so they enter flex/modules's morph map (built in
        // ModulesServiceProvider's app.booted callback, D22).
        $registry = $this->app->make(ArrayEntityRegistry::class);

        $registry->register(new EntityDefinition(
            handle: 'tsk.process',
            model: Process::class,
            table: 'tsk_processes',
            module: 'corex/tasks',
            labelSingular: 'Process',
            labelPlural: 'Processes',
            capabilities: [],
            fields: [],
            titleAttribute: 'name',
        ));

        $registry->register(new EntityDefinition(
            handle: 'tsk.stage',
            model: Stage::class,
            table: 'tsk_stages',
            module: 'corex/tasks',
            labelSingular: 'Stage',
            labelPlural: 'Stages',
            capabilities: [],
            fields: [],
            titleAttribute: 'name',
        ));

        $registry->register(new EntityDefinition(
            handle: 'tsk.project',
            model: Project::class,
            table: 'tsk_projects',
            module: 'corex/tasks',
            labelSingular: 'Project',
            labelPlural: 'Projects',
            capabilities: [],
            fields: [],
            titleAttribute: 'name',
        ));

        $registry->register(new EntityDefinition(
            handle: 'tsk.task',
            model: Task::class,
            table: 'tsk_tasks',
            module: 'corex/tasks',
            labelSingular: 'Task',
            labelPlural: 'Tasks',
            capabilities: [Capability::Auditable],
            fields: [],
            titleAttribute: 'title',
        ));
    }
}
