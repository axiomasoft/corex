<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Data\ModuleRuntimeContext;

/**
 * Module-authored seed step run on enable. MUST be idempotent — a retry
 * after a failed enable re-runs it.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface EnableHook
{
    public function __invoke(ModuleRuntimeContext $ctx): void;
}
