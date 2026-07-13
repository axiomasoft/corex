<?php

declare(strict_types=1);

namespace Flex\Modules\Events;

/**
 * Interim event base (workaround, OQ-4): `DomainEvent` ships in P1.9, which
 * lands after this item — these lifecycle events are plain Laravel events
 * for now and are retrofitted onto `DomainEvent` there (handoff.md
 * Workarounds). Dispatched on a successful `ModuleLifecycle::enable()`.
 */
final readonly class ModuleEnabled
{
    /** @param  list<string>  $migrationsRun */
    public function __construct(
        public string $module,
        public string $edition,
        public array $migrationsRun,
    ) {}
}
