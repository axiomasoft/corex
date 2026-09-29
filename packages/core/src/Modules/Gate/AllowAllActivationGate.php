<?php

declare(strict_types=1);

namespace CoreX\Modules\Gate;

use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\ModuleActivationGate;

/**
 * Boxed default — no billing gate exists outside cloud, so every
 * module/edition is allowed. The application injects a cloud-aware
 * implementation for SaaS installs.
 *
 * @internal spec: D10
 */
final class AllowAllActivationGate implements ModuleActivationGate
{
    public function check(TenantContext $ctx, string $module, string $edition): ModuleActivationDecision
    {
        return ModuleActivationDecision::allow();
    }
}
