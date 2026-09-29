<?php

declare(strict_types=1);

namespace CoreX\Tools;

use CoreX\Modules\Contracts\ManifestSectionCompiler;
use CoreX\Tools\Contracts\ToolCatalogue;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Contracts\ToolSchemaValidator;
use CoreX\Tools\Internal\CompiledToolCatalogue;
use CoreX\Tools\Internal\DenyToolPolicy;
use CoreX\Tools\Internal\GuardedToolInvoker;
use CoreX\Tools\Internal\ReadOnlyToolExecutionGuard;
use CoreX\Tools\Internal\ToolSchemaSupervisor;
use CoreX\Tools\Internal\ToolSectionCompiler;
use Illuminate\Support\ServiceProvider;
use Override;

/** Explicit tool surface; no routes, migrations or auto-discovery. */
final class ToolServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([ToolSectionCompiler::class], ManifestSectionCompiler::class);

        $this->app->bindIf(ToolPolicy::class, DenyToolPolicy::class);
        $this->app->bindIf(ToolCatalogue::class, CompiledToolCatalogue::class);
        $this->app->bindIf(ToolSchemaValidator::class, ToolSchemaSupervisor::class);
        $this->app->bindIf(ToolExecutionGuard::class, ReadOnlyToolExecutionGuard::class);
        $this->app->bindIf(ToolInvoker::class, GuardedToolInvoker::class);
    }
}
