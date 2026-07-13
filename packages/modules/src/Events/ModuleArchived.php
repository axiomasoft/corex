<?php

declare(strict_types=1);

namespace Flex\Modules\Events;

/** Interim event base (workaround, OQ-4 — see {@see ModuleEnabled}). */
final readonly class ModuleArchived
{
    public function __construct(public string $module) {}
}
