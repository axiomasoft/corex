<?php

declare(strict_types=1);

namespace CoreX\Api;

use CoreX\Api\Contracts\ToolCatalogue;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Contracts\ToolInvoker;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Contracts\ToolSchemaValidator;
use CoreX\Api\Internal\CompiledToolCatalogue;
use CoreX\Api\Internal\DenyToolPolicy;
use CoreX\Api\Internal\GuardedToolInvoker;
use CoreX\Api\Internal\ReadOnlyToolExecutionGuard;
use CoreX\Api\Internal\ToolSchemaSupervisor;
use Illuminate\Support\ServiceProvider;
use Override;

/** Explicit tool surface; no routes, migrations or auto-discovery. */
final class ToolServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bindIf(ToolPolicy::class, DenyToolPolicy::class);
        $this->app->bindIf(ToolCatalogue::class, CompiledToolCatalogue::class);
        $this->app->bindIf(ToolSchemaValidator::class, ToolSchemaSupervisor::class);
        $this->app->bindIf(ToolExecutionGuard::class, ReadOnlyToolExecutionGuard::class);
        $this->app->bindIf(ToolInvoker::class, GuardedToolInvoker::class);
    }
}
