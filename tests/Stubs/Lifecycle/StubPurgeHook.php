<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use Flex\Modules\Contracts\PurgeHook;
use Flex\Modules\ModuleContext;

/** No non-standard tail to clean up — table/setting teardown is `mod_records`-driven. */
final class StubPurgeHook implements PurgeHook
{
    public function __invoke(ModuleContext $ctx): void {}
}
