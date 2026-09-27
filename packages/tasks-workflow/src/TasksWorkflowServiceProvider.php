<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Tasks\Contracts\CreateTaskCommand;
use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Models\Task;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Internal\CaptureTaskCompletion;
use CoreX\TasksWorkflow\Internal\SqlTaskRecurrenceMaterializer;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class TasksWorkflowServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-task-recurrence.php', 'corex-task-recurrence');

        $this->app->singleton(RecurrenceExpander::class, 'CoreX\Recurrence\Internal\RRuleRecurrenceExpander');

        $this->app->scoped(TaskRecurrenceCommand::class, function (Application $app): SqlTaskRecurrenceMaterializer {
            return new SqlTaskRecurrenceMaterializer(
                connection: $app['db']->connection(),
                expander: $app->make(RecurrenceExpander::class),
                createTask: $app->make(CreateTaskCommand::class),
                taskAccess: $app->make(TaskAccess::class),
                config: $app['config'],
                container: $app,
                authorizer: $app->make(DataScopeAuthorizer::class),
                contexts: $app->make(TenantContextRestorer::class),
                receipts: $app->make(TaskOperationReceipts::class),
            );
        });
    }

    public function boot(): void
    {
        Task::observe($this->app->make(CaptureTaskCompletion::class));
    }
}
