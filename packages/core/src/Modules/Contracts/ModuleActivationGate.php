<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Internal\Gate\ModuleActivationDecision;

/**
 * Cloud billing/edition gate. Boxed installs bind
 * {@see CoreX\Modules\Internal\Gate\AllowAllActivationGate} — the cloud implementation (root_ billing)
 * is injected by the application, never known to this package.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ModuleActivationGate
{
    public function check(TenantContext $ctx, string $module, string $edition): ModuleActivationDecision;
}
