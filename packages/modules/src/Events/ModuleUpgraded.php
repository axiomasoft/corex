<?php

declare(strict_types=1);

namespace Flex\Modules\Events;

/** Interim event base (workaround, OQ-4 — see {@see ModuleEnabled}). */
final readonly class ModuleUpgraded
{
    /** @param  list<string>  $migrationsRun */
    public function __construct(
        public string $module,
        public string $fromVersion,
        public string $toVersion,
        public array $migrationsRun,
    ) {}
}
