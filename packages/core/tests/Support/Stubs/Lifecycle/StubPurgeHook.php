<?php

declare(strict_types=1);

namespace CoreX\Testing\Stubs\Lifecycle;

use CoreX\Modules\Contracts\PurgeHook;
use CoreX\Modules\Definition\ModuleRuntimeContext;

/** No non-standard tail to clean up — table/setting teardown is `mod_records`-driven. */
final class StubPurgeHook implements PurgeHook
{
    public function __invoke(ModuleRuntimeContext $ctx): void {}
}
