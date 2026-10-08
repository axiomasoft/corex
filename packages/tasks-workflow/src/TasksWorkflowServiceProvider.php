<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\TasksWorkflow\Errors\Internal\PackageProblemDefinitions;
use CoreX\TasksWorkflow\Internal\DangerousModesCheck;
use CoreX\TasksWorkflow\Internal\MaterializationBindings;
use CoreX\TasksWorkflow\Internal\TasksWorkflowOptions;
use CoreX\TasksWorkflow\Internal\TasksWorkflowSchemaSets;
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
        $this->app->tag([TasksWorkflowSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-tasks-workflow.php', 'corex-tasks-workflow');

        $this->app->singleton(TasksWorkflowOptions::class, static fn (Application $app): TasksWorkflowOptions => TasksWorkflowOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-tasks-workflow', []),
        ));
        // Register-time read: the wakeup tag must be decided before the registry is built.
        $options = TasksWorkflowOptions::fromArray((array) $this->app->make(Repository::class)->get('corex-tasks-workflow', []));
        $this->app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);

        MaterializationBindings::register(app: $this->app, wakeupEnabled: $options->wakeupEnabled);
    }

    public function boot(): void
    {
        $this->app->make(TasksWorkflowOptions::class);

        CompletionBindings::register($this->app);
    }
}
