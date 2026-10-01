<?php

declare(strict_types=1);

namespace CoreX\Modules\Data;

use CoreX\Attributes\Api;
use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\RecordsRegistrar;

/**
 * Payload passed to {@see Contracts\EnableHook}/{@see Contracts\DisableHook}/
 * {@see Contracts\PurgeHook}: which module, for which tenant, with the
 * registrar the hook must use to claim/release anything it creates.
 */
#[Api(since: '1.0')]
final readonly class ModuleRuntimeContext
{
    public function __construct(
        public string $module,
        public TenantContext $tenant,
        public RecordsRegistrar $records,
    ) {}
}
