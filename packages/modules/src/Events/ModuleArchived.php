<?php

declare(strict_types=1);

namespace Flex\Modules\Events;

use CoreX\Events\DomainEvent;
use Override;

/** Retrofitted onto `DomainEvent` (P1.25, A68 — see {@see ModuleEnabled}). `mod.module.archived` (B-10 §3.1). */
final class ModuleArchived extends DomainEvent
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
