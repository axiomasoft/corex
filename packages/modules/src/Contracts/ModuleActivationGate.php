<?php

declare(strict_types=1);

namespace Flex\Modules\Contracts;

use CoreX\Tenancy\TenantContext;
use Flex\Modules\Gate\AllowAllActivationGate;
use Flex\Modules\GateDecision;

/**
 * Cloud billing/edition gate (B-10 §3.3). Boxed installs bind
 * {@see AllowAllActivationGate} (D10) — the cloud
 * implementation (root_ billing) is injected by the application, never
 * known to this package.
 */
interface ModuleActivationGate
{
    public function check(TenantContext $ctx, string $module, string $edition): GateDecision;
}
