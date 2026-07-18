<?php

declare(strict_types=1);

namespace CoreX\Modules\Events;

use CoreX\Events\DomainEvent;
use Override;

/** Retrofitted onto `DomainEvent` (P1.25, A68 — see {@see ModuleEnabled}). `mod.module.upgraded` (B-10 §3.1). */
final class ModuleUpgraded extends DomainEvent
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
