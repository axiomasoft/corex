<?php

declare(strict_types=1);

namespace CoreX\Workflow\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Internal\SqlWorkflowBinding;
use CoreX\Workflow\Internal\UnavailableEngineAdapter;
use CoreX\Workflow\Internal\WorkflowOptions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Psr\Clock\ClockInterface;

/** Builds the workflow binding from the container; the engine adapter and module registry are optional. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WorkflowBindingFactory
{
    public function make(Application $app): SqlWorkflowBinding
    {
        $engine = $app->bound(EngineAdapter::class)
            ? $app->make(EngineAdapter::class)
            : new UnavailableEngineAdapter;

        return new SqlWorkflowBinding(
            connection: $app->make(DatabaseManager::class)->connection(),
            contexts: $app->make(TenantContextResolver::class),
            engine: $engine,
            modules: $app->bound(ModuleRegistry::class) ? $app->make(ModuleRegistry::class) : null,
            enforceDeclarations: $app->make(WorkflowOptions::class)->enforceDeclarations,
            clock: $app->make(ClockInterface::class),
        );
    }
}
