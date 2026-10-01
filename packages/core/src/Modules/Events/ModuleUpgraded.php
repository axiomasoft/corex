<?php

declare(strict_types=1);

namespace CoreX\Modules\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Events\DomainEvent;
use Override;

/**
 * Retrofitted onto `DomainEvent` (see {@see ModuleEnabled}). `mod.module.upgraded`.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ModuleUpgraded extends DomainEvent
{
    /** @param  list<string>  $migrationsRun */
    public function __construct(
        public readonly string $module,
        public readonly string $fromVersion,
        public readonly string $toVersion,
        public readonly array $migrationsRun,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'mod.module.upgraded';
    }
}
