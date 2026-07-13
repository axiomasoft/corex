<?php

declare(strict_types=1);

namespace Flex\Modules;

use CoreX\Tenancy\TenantContext;
use Flex\Modules\Contracts\RecordsRegistrar;

/**
 * Payload passed to {@see Contracts\EnableHook}/{@see Contracts\DisableHook}/
 * {@see Contracts\PurgeHook} (B-10 §3.3): which module, for which tenant,
 * with the registrar the hook must use to claim/release anything it creates.
 */
final readonly class ModuleContext
{
    public function __construct(
        public string $module,
        public TenantContext $tenant,
        public RecordsRegistrar $records,
    ) {}
}
