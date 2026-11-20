<?php

declare(strict_types=1);

namespace CoreX\Workflow\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Execution\Internal\SqlWorkflowBinding;
use CoreX\Workflow\Execution\Internal\UnavailableEngineAdapter;
use CoreX\Workflow\Execution\Internal\WorkflowIntentStore;
use CoreX\Workflow\Internal\WorkflowOptions;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Psr\Clock\ClockInterface;

/** Builds the workflow binding from the container; the engine adapter and module registry are optional. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WorkflowBindingFactory
{
    public function make(Application $app): WorkflowBinding
    {
        return new SqlWorkflowBinding(
            scopes: $app->make(CurrentWorkScope::class),
            intents: $app->make(WorkflowIntentStore::class),
            contexts: $app->make(TenantContextResolver::class),
            engine: $app->bound(EngineAdapter::class) ? $app->make(EngineAdapter::class) : new UnavailableEngineAdapter,
            modules: $app->bound(ModuleRegistry::class) ? $app->make(ModuleRegistry::class) : null,
            enforceDeclarations: $app->make(WorkflowOptions::class)->enforceDeclarations,
            clock: $app->make(ClockInterface::class),
        );
    }
}
