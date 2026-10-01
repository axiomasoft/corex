<?php

declare(strict_types=1);

namespace CoreX\Modules\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Events\DomainEvent;
use Override;

/**
 * Retrofitted onto `DomainEvent` (see {@see ModuleEnabled}). `mod.module.purged`.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ModulePurged extends DomainEvent
{
    /** @param  list<string>  $droppedTables */
    public function __construct(
        public readonly string $module,
        public readonly array $droppedTables,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'mod.module.purged';
    }
}
