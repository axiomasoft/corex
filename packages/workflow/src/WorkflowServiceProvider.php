<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Internal\DangerousModesCheck;
use CoreX\Workflow\Internal\SqlWorkflowBinding;
use CoreX\Workflow\Internal\WorkflowOptions;
use CoreX\Workflow\Internal\WorkflowSchemaSets;
use CoreX\Workflow\Laravel\WorkflowBindingFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WorkflowServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([WorkflowSchemaSets::class], SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-workflow.php', 'corex-workflow');
        $this->app->singleton(WorkflowOptions::class, static fn (Application $app): WorkflowOptions => WorkflowOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-workflow', []),
        ));
        $this->app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);
        $this->app->scoped(WorkflowBinding::class, static fn (Application $app): SqlWorkflowBinding => (new WorkflowBindingFactory)->make($app));
    }

    public function boot(): void
    {
        $this->app->make(WorkflowOptions::class);
    }
}
