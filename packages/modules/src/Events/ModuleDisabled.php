<?php

declare(strict_types=1);

namespace CoreX\Modules\Events;

use CoreX\Events\DomainEvent;
use CoreX\Modules\Enums\DisableReason;
use Override;

/** Retrofitted onto `DomainEvent` (P1.25, A68 — see {@see ModuleEnabled}). `mod.module.disabled` (B-10 §3.1). */
final class ModuleDisabled extends DomainEvent
{
    public function __construct(
        public readonly string $module,
        public readonly DisableReason $reason,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'mod.module.disabled';
    }
}
