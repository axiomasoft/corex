<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Modules\ModuleContext;

/**
 * Module-authored seed step run on enable (B-10 §3.3/§5.3). MUST be
 * idempotent — a retry after a failed enable re-runs it (B-10 §7.4 п.6).
 */
interface EnableHook
{
    public function __invoke(ModuleContext $ctx): void;
}
