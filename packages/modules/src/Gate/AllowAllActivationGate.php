<?php

declare(strict_types=1);

namespace Flex\Modules\Gate;

use CoreX\Tenancy\TenantContext;
use Flex\Modules\Contracts\ModuleActivationGate;
use Flex\Modules\GateDecision;

/**
 * Boxed default (D10) — no billing gate exists outside cloud, so every
 * module/edition is allowed. The application injects a cloud-aware
 * implementation for SaaS installs.
 */
final class AllowAllActivationGate implements ModuleActivationGate
{
    public function check(TenantContext $ctx, string $module, string $edition): GateDecision
    {
        return GateDecision::allow();
    }
}
