<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Laravel\RecurrenceFactory;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

/**
 * Registers the materialization port and, when enabled, its tagged wakeup handler.
 * `$wakeupEnabled` is read once at register time: tagging decides what the wakeup registry sees.
 *
 * @internal Composition detail of {@see RecurringTasksServiceProvider}.
 */
#[Internal]
final class MaterializationBindings
{
    private const string WAKEUP_HANDLER = 'corex.recurring-tasks.materialization-wakeup-handler';

    public static function register(Application $app, bool $wakeupEnabled): void
    {
        $app->scoped(MaterializationWakeupHandler::class);

        if ($wakeupEnabled) {
            $app->singleton(self::WAKEUP_HANDLER, static fn (Application $app): WakeupHandlerDefinition => $app->make(RecurringTasksOptions::class)->wakeupHandlerDefinition());
            $app->tag(self::WAKEUP_HANDLER, WakeupHandlerDefinition::TAG);
        }

        $app->singleton(RecurrenceExpander::class, static fn (): RecurrenceExpander => RecurrenceFactory::create());
        $app->scoped(TaskRecurrenceCommand::class, static fn (Application $app): SqlTaskRecurrenceMaterializer => new SqlTaskRecurrenceMaterializer(
            connection: $app->make(DatabaseManager::class)->connection(),
            expander: $app->make(RecurrenceExpander::class),
            createTask: $app->make(CreateTaskCommand::class),
            taskAccess: $app->make(TaskAccess::class),
            wakeupEnabled: $app->make(RecurringTasksOptions::class)->wakeupEnabled,
            wakeupHandlerKind: $app->make(RecurringTasksOptions::class)->wakeupHandlerKind,
            wakeupHandlerVersion: $app->make(RecurringTasksOptions::class)->wakeupHandlerVersion,
            container: $app,
            authorizer: $app->make(DataScopeAuthorizer::class),
            contexts: $app->make(CurrentWorkScope::class),
            receipts: $app->make(TaskOperationReceipts::class),
        ));
    }
}
