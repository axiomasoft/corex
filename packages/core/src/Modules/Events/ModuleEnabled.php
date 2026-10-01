<?php

declare(strict_types=1);

namespace CoreX\Modules\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Events\DomainEvent;
use Override;

/**
 * Retrofitted onto `DomainEvent` — was an interim plain-event class
 * dispatched on a successful `ModuleLifecycle::enable()`. Name matches the
 * domain-events table (`mod.module.enabled`).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ModuleEnabled extends DomainEvent
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
