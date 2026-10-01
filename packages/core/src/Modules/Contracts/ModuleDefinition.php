<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Data\ModuleManifest;

/**
 * Base class of a CoreX module: a concrete subclass describes itself
 * declaratively via manifest(), discovered from `installed.json`
 * (extra.corex.module) — no imperative registration.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
abstract class ModuleDefinition
{
    abstract public function manifest(): ModuleManifest;
}
