<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use CoreX\Modules\Contracts\DisableHook;
use CoreX\Modules\ModuleContext;

/** No data side effects (B-10 §7.4 п.1) — nothing to tear down here. */
final class StubDisableHook implements DisableHook
{
    public function __invoke(ModuleContext $ctx): void {}
}
