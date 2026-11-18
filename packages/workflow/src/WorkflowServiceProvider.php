<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Workflow\Contracts\WorkflowBinding;
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
        $this->app->tag((new WorkflowModule)->schemaSetProviders(), SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-workflow.php', 'corex-workflow');
        $this->app->singleton(WorkflowOptions::class, static fn (Application $app): WorkflowOptions => WorkflowOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-workflow', []),
        ));
        $this->app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);
        $this->app->scoped(WorkflowBinding::class, static fn (Application $app): WorkflowBinding => (new WorkflowBindingFactory)->make($app));
    }

    public function boot(): void
    {
        $this->app->make(WorkflowOptions::class);
    }
}
