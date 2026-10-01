<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Data\ModuleRuntimeContext;

/**
 * Module-authored teardown of non-data side effects on disable (cron,
 * webhooks). MUST NOT touch the module's data.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DisableHook
{
    public function __invoke(ModuleRuntimeContext $ctx): void;
}
