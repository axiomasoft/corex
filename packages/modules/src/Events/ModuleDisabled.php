<?php

declare(strict_types=1);

namespace Flex\Modules\Events;

use Flex\Modules\Enums\DisableReason;

/** Interim event base (workaround, OQ-4 — see {@see ModuleEnabled}). */
final readonly class ModuleDisabled
{
    public function __construct(
        public string $module,
        public DisableReason $reason,
    ) {}
}
