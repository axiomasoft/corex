<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow;

use CoreX\TasksWorkflow\Completion\Internal\TasksWorkflowOptions;
use CoreX\TasksWorkflow\Completion\Laravel\CompletionRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class TasksWorkflowServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $file = __DIR__.'/../config/corex-tasks-workflow.php';
        $this->mergeConfigFrom($file, 'corex-tasks-workflow');
        $this->app->singleton(TasksWorkflowOptions::class, static fn (Application $app): TasksWorkflowOptions => TasksWorkflowOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-tasks-workflow', []),
        ));
        $this->publishes([$file => $this->app->configPath('corex-tasks-workflow.php')], 'corex-tasks-workflow-config');
        (new CompletionRegistrar)->register($this->app);
    }

    public function boot(): void
    {
        (new CompletionRegistrar)->boot($this->app);
    }
}
