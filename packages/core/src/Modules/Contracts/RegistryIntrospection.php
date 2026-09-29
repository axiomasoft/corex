<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Data\ModuleExplanation;
use CoreX\Modules\Data\RegistryMap;

/**
 * Read-only registry diagnostics over compiled module bytes and current
 * activation state. No state writes, provider reflection or network access.
 */
interface RegistryIntrospection
{
    public function extensionPoints(): RegistryMap;

    public function why(string $module, ?TenantContext $context): ModuleExplanation;
}
