<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Data\ModuleRuntimeContext;

/**
 * Module-authored non-standard cleanup on purge — external webhooks, media
 * files — anything outside what `mod_records` can drop by itself.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface PurgeHook
{
    public function __invoke(ModuleRuntimeContext $ctx): void;
}
