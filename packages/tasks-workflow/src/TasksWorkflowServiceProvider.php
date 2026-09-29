<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Models\Task;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Internal\CaptureTaskCompletion;
use CoreX\TasksWorkflow\Internal\SqlTaskRecurrenceMaterializer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class TasksWorkflowServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-tasks-workflow.php', 'corex-tasks-workflow');

        $this->app->singleton(RecurrenceExpander::class, 'CoreX\Recurrence\RRuleRecurrenceExpander');

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
