<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow;

use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Internal\RRuleRecurrenceExpander;
use CoreX\Tasks\Contracts\CreateTaskCommand;
use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Models\Task;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Internal\CompletionCaptureObserver;
use CoreX\TasksWorkflow\Internal\SqlTaskRecurrenceMaterializer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class TasksWorkflowServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-task-recurrence.php', 'corex-task-recurrence');

        $this->app->singleton(RecurrenceExpander::class, RRuleRecurrenceExpander::class);

        $this->app->scoped(TaskRecurrenceCommand::class, function (Application $app): SqlTaskRecurrenceMaterializer {
            return new SqlTaskRecurrenceMaterializer(
                connection: $app['db']->connection(),
                expander: $app->make(RecurrenceExpander::class),
                createTask: $app->make(CreateTaskCommand::class),
                taskAccess: $app->make(TaskAccess::class),
                config: $app['config'],
                container: $app,
            );
        });
    }

    public function boot(): void
    {
        Task::observe($this->app->make(CompletionCaptureObserver::class));
    }
}
