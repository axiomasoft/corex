<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

/**
 * No-dependency module used across ModuleLifecycleTest: two migrations
 * (claiming their tables via RecordsRegistrar) + idempotent hooks — enough
 * to exercise enable/disable/archive/purge.
 */
final class StubBaseModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/stub-base')
            ->title('Stub Base')
            ->tablePrefix('sba')
            ->onEnable(StubEnableHook::class)
            ->onDisable(StubDisableHook::class)
            ->onPurge(StubPurgeHook::class);
    }
}
