<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Laravel\RecurrenceFactory;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;

/**
 * Registers the materialization port and, when enabled, its tagged wakeup handler.
 * `$wakeupEnabled` is read once at register time: tagging decides what the wakeup registry sees.
 *
 * @internal Composition detail of {@see TasksWorkflowServiceProvider}.
 */
final class MaterializationBindings
{
    private const string WAKEUP_HANDLER = 'corex.tasks-workflow.materialization-wakeup-handler';

    public static function register(Application $app, bool $wakeupEnabled): void
    {
        $app->scoped(MaterializationWakeupHandler::class);

        if ($wakeupEnabled) {
            $app->singleton(self::WAKEUP_HANDLER, static fn (Application $app): WakeupHandlerDefinition => $app->make(TasksWorkflowOptions::class)->wakeupHandlerDefinition());
            $app->tag(self::WAKEUP_HANDLER, WakeupHandlerDefinition::TAG);
        }

        $app->singleton(RecurrenceExpander::class, static fn (): RecurrenceExpander => RecurrenceFactory::create());
        $app->scoped(TaskRecurrenceCommand::class, static fn (Application $app): SqlTaskRecurrenceMaterializer => new SqlTaskRecurrenceMaterializer(
            connection: $app->make(DatabaseManager::class)->connection(),
            expander: $app->make(RecurrenceExpander::class),
            createTask: $app->make(CreateTaskCommand::class),
            taskAccess: $app->make(TaskAccess::class),
            wakeupEnabled: $app->make(TasksWorkflowOptions::class)->wakeupEnabled,
            wakeupHandlerKind: $app->make(TasksWorkflowOptions::class)->wakeupHandlerKind,
            container: $app,
            authorizer: $app->make(DataScopeAuthorizer::class),
            contexts: $app->make(TenantContextRestorer::class),
            receipts: $app->make(TaskOperationReceipts::class),
        ));
    }
}
