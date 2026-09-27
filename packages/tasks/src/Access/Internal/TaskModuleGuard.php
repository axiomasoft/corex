<?php

declare(strict_types=1);

namespace CoreX\Tasks\Support;

use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tasks\Exceptions\TasksModuleDisabled;
use CoreX\Tasks\TasksModule;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;

final class TaskModuleGuard
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly TenantContextResolver $tenantContextResolver,
    ) {}

    public function context(): TenantContext
    {
        $context = $this->tenantContextResolver->current();

        if (! $this->modules->isActive(TasksModule::NAME, $context)) {
            throw TasksModuleDisabled::forCurrentAccount();
        }

        return $context;
    }
}
