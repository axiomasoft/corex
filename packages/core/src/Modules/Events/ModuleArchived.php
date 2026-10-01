<?php

declare(strict_types=1);

namespace CoreX\Modules\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Events\DomainEvent;
use Override;

/**
 * Retrofitted onto `DomainEvent` (see {@see ModuleEnabled}). `mod.module.archived`.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ModuleArchived extends DomainEvent
{
    public function __construct(public readonly string $module)
    {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'mod.module.archived';
    }
}
