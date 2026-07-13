<?php

declare(strict_types=1);

namespace Flex\Modules\Contracts;

use Flex\Modules\ModuleContext;

/**
 * Module-authored teardown of non-data side effects on disable (cron,
 * webhooks — B-10 §5.4). MUST NOT touch the module's data (B-10 §7.4 п.1).
 */
interface DisableHook
{
    public function __invoke(ModuleContext $ctx): void;
}
