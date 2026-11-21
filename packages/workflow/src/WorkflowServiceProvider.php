<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Execution\Internal\SqlWorkflowBinding;
use CoreX\Workflow\Execution\Internal\UnavailableEngineAdapter;
use CoreX\Workflow\Execution\Internal\WorkflowModuleReferences;
use CoreX\Workflow\Execution\Internal\WorkflowReceiptProjector;
use CoreX\Workflow\Execution\Laravel\Commands\SweepWorkflowIntents;
use CoreX\Workflow\Internal\DangerousModesCheck;
use CoreX\Workflow\Internal\WorkflowOptions;
use CoreX\Workflow\Laravel\WorkflowBindingFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Contracts\RuntimeConfigCheck;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WorkflowServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag(WorkflowModuleReferences::class, ModuleReferences::class);

        $this->app->tag((new WorkflowModule)->schemaSetProviders(), SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-workflow.php', 'corex-workflow');
        $this->app->singleton(WorkflowOptions::class, static fn (Application $app): WorkflowOptions => WorkflowOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-workflow', []),
        ));
        $this->app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);
        $this->app->bindIf(EngineAdapter::class, UnavailableEngineAdapter::class);
        $this->app->scoped(SqlWorkflowBinding::class, static fn (Application $app): WorkflowBinding => (new WorkflowBindingFactory)->make($app));
        $this->app->alias(SqlWorkflowBinding::class, WorkflowBinding::class);
        $this->app->tag(WorkflowReceiptProjector::class, ProviderReceiptProjector::class);
        $this->commands([SweepWorkflowIntents::class]);
    }

    public function boot(): void
    {
        $this->app->make(WorkflowOptions::class);
    }
}
