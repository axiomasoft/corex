<?php

declare(strict_types=1);

namespace CoreX\Tasks\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Access\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Access\Internal\DenyTaskAccess;
use CoreX\Tasks\Access\Internal\DenyTaskMutationAccess;
use CoreX\Tasks\Access\Internal\DenyTaskPlanningAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Contracts\TaskPlanning;
use CoreX\Tasks\Planning\Internal\PlanningOperationsServiceProvider;
use CoreX\Tasks\Planning\Laravel\PlanningCommand;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Contracts\UpdateTaskCommand;
use CoreX\Tasks\Task\Internal\Actions\CompleteTask;
use CoreX\Tasks\Task\Internal\Actions\CreateTask;
use CoreX\Tasks\Task\Internal\Actions\MoveTaskToStage;
use CoreX\Tasks\Task\Internal\Actions\UpdateTask;
use CoreX\Tasks\Task\Internal\TaskCompletion;
use CoreX\Tasks\Task\Internal\TaskModuleReferences;
use CoreX\Tasks\Task\Laravel\TaskPortBindings;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TasksRegistrar implements PackageRegistrar
{
    public function register(Application $app): void
    {
        $app->scoped(TaskModuleReferences::class);
        $app->tag(TaskModuleReferences::class, ModuleReferences::class);

        TaskPortBindings::register($app);

        $app->bind(CreateTaskCommand::class, CreateTask::class);
        $app->bind(MoveTaskToStageCommand::class, MoveTaskToStage::class);
        $app->bind(UpdateTaskCommand::class, UpdateTask::class);
        $app->bind(CompleteTaskCommand::class, CompleteTask::class);
        $app->bind(TaskAccess::class, DenyTaskAccess::class);
        $app->bind(TaskMutationAccess::class, DenyTaskMutationAccess::class);
        $app->bind(TaskPlanning::class, PlanningCommand::class);
        $app->bind(TaskPlanningAccess::class, DenyTaskPlanningAccess::class);
        $app->register(PlanningOperationsServiceProvider::class);

        $app->bind(TaskCompletion::class, static fn (Application $app): TaskCompletion => new TaskCompletion(
            access: $app->make(TaskMutationAccess::class),
            clock: $app->make(ClockInterface::class),
            scopes: $app->make(DataScopeFactory::class),
            workScope: $app->make(CurrentWorkScope::class),
            receipts: $app->make(TaskOperationReceipts::class),
            capture: self::completionCapture($app),
        ));
    }

    public function boot(Application $app): void
    {
        $app->booted(static fn () => self::assertCaptureRegistrations($app));
    }

    private static function assertCaptureRegistrations(Application $app): void
    {
        $captures = $app->tagged(TaskCompletionCapture::TAG);

        // Laravel counts tag registrations without resolving their scoped factories.
        if (is_countable($captures) && count($captures) > 1) {
            throw new LogicException('Tasks accepts at most one task completion capture contributor.');
        }
    }

    private static function completionCapture(Application $app): ?TaskCompletionCapture
    {
        $captures = $app->tagged(TaskCompletionCapture::TAG);

        if (is_countable($captures) && count($captures) > 1) {
            throw new LogicException('Tasks accepts at most one task completion capture contributor.');
        }

        $capture = null;

        foreach ($captures as $registered) {
            if ($capture !== null) {
                throw new LogicException('Tasks accepts at most one task completion capture contributor.');
            }

            if (! $registered instanceof TaskCompletionCapture) {
                throw new LogicException('The task completion contributor must implement TaskCompletionCapture.');
            }

            $capture = $registered;
        }

        return $capture;
    }
}
