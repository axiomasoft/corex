<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tasks\Access\Contracts\TaskModuleContext;
use CoreX\Tasks\Access\Exceptions\TasksModuleDisabled;
use CoreX\Tasks\TasksModule;

final readonly class TaskModuleGuard implements TaskModuleContext
{
    public function __construct(
        private ModuleRegistry $modules,
        private TenantContextResolver $tenantContextResolver,
    ) {}

    public function context(): TenantContext
    {
        $context = $this->tenantContextResolver->current();

        if (! $this->modules->isActive(name: TasksModule::NAME, ctx: $context)) {
            throw TasksModuleDisabled::forCurrentAccount();
        }

        return $context;
    }
}
