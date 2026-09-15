<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;

/**
 * No-dependency module used across ModuleLifecycleTest: two migrations
 * (claiming their tables via RecordsRegistrar) + idempotent hooks — enough
 * to exercise enable/disable/archive/purge (AC-5, AC-8..AC-11).
 */
final class StubBaseModule extends CorexModule
{
    public function manifest(): Manifest
    {
        return Manifest::make('corex/stub-base')
            ->title('Stub Base')
            ->tablePrefix('sba')
            ->onEnable(StubEnableHook::class)
            ->onDisable(StubDisableHook::class)
            ->onPurge(StubPurgeHook::class);
    }
}
