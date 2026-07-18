<?php

declare(strict_types=1);

namespace CoreX\Modules\Events;

use CoreX\Events\DomainEvent;
use Override;

/**
 * Retrofitted onto `DomainEvent` (P1.25, A68) — was an interim plain-event
 * class (OQ-4 workaround) dispatched on a successful
 * `ModuleLifecycle::enable()`. Name matches the B-10 §3.1 domain-events
 * table (`mod.module.enabled`).
 */
final class ModuleEnabled extends DomainEvent
{
    /** @param  list<string>  $migrationsRun */
    public function __construct(
        public readonly string $module,
        public readonly string $edition,
        public readonly array $migrationsRun,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'mod.module.enabled';
    }
}
