<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Laravel\RecurrenceFactory;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Errors\Internal\PackageProblemDefinitions;
use CoreX\TasksWorkflow\Internal\DangerousModesCheck;
use CoreX\TasksWorkflow\Internal\SqlTaskRecurrenceMaterializer;
use CoreX\TasksWorkflow\Internal\TasksWorkflowOptions;
use CoreX\TasksWorkflow\Laravel\CompletionBindings;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TasksWorkflowServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-tasks-workflow.php', 'corex-tasks-workflow');

        $this->app->singleton(TasksWorkflowOptions::class, static fn (Application $app): TasksWorkflowOptions => TasksWorkflowOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-tasks-workflow', []),
        ));
        $this->app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);

        $this->app->singleton(RecurrenceExpander::class, static fn (): RecurrenceExpander => RecurrenceFactory::create());

        $this->app->scoped(TaskRecurrenceCommand::class, function (Application $app): SqlTaskRecurrenceMaterializer {
            return new SqlTaskRecurrenceMaterializer(
                connection: $app['db']->connection(),
                expander: $app->make(RecurrenceExpander::class),
                createTask: $app->make(CreateTaskCommand::class),
                taskAccess: $app->make(TaskAccess::class),
                wakeupEnabled: $app->make(TasksWorkflowOptions::class)->wakeupEnabled,
                wakeupHandlerKind: $app->make(TasksWorkflowOptions::class)->wakeupHandlerKind,
                container: $app,
                authorizer: $app->make(DataScopeAuthorizer::class),
                contexts: $app->make(TenantContextRestorer::class),
                receipts: $app->make(TaskOperationReceipts::class),
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(TasksWorkflowOptions::class);

        CompletionBindings::register($this->app);
    }
}
