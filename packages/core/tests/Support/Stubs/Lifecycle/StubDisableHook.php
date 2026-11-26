<?php

declare(strict_types=1);

namespace CoreX\Testing\Stubs\Lifecycle;

use Packman\Modules\Contracts\Lifecycle\DisableHook;
use Packman\Modules\Data\Lifecycle\ModuleRuntimeContext;

/** No data side effects — nothing to tear down here. */
final class StubDisableHook implements DisableHook
{
    public function __invoke(ModuleRuntimeContext $ctx): void {}
}
