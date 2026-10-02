<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Internal\DangerousModesCheck;
use CoreX\Workflow\Internal\SqlWorkflowBinding;
use CoreX\Workflow\Internal\UnavailableEngineAdapter;
use CoreX\Workflow\Internal\WorkflowOptions;
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
        $this->mergeConfigFrom(__DIR__.'/../config/corex-workflow.php', 'corex-workflow');
        $this->app->singleton(WorkflowOptions::class, static fn (Application $app): WorkflowOptions => WorkflowOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-workflow', []),
        ));
        $this->app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);
        $this->app->scoped(WorkflowBinding::class, function (Application $app): SqlWorkflowBinding {
            $engine = $app->bound(EngineAdapter::class)
                ? $app->make(EngineAdapter::class)
                : new UnavailableEngineAdapter;

            return new SqlWorkflowBinding(
                connection: $app['db']->connection(),
                contexts: $app->make(TenantContextResolver::class),
                engine: $engine,
                modules: $app->bound(ModuleRegistry::class) ? $app->make(ModuleRegistry::class) : null,
                enforceDeclarations: $app->make(WorkflowOptions::class)->enforceDeclarations,
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(WorkflowOptions::class);
    }
}
