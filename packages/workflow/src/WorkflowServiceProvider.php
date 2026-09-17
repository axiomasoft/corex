<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Internal\SqlWorkflowBinding;
use CoreX\Workflow\Internal\UnavailableEngineAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class WorkflowServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-workflow.php', 'corex-workflow');
        $this->app->scoped(WorkflowBinding::class, function (Application $app): SqlWorkflowBinding {
            $engine = $app->bound(EngineAdapter::class)
                ? $app->make(EngineAdapter::class)
                : new UnavailableEngineAdapter;

            return new SqlWorkflowBinding(
                connection: $app['db']->connection(),
                contexts: $app->make(TenantContextResolver::class),
                engine: $engine,
                modules: $app->bound(ModuleRegistry::class) ? $app->make(ModuleRegistry::class) : null,
                enforceDeclarations: (bool) $app['config']->get('corex-workflow.enforce_declarations'),
            );
        });
    }
}
