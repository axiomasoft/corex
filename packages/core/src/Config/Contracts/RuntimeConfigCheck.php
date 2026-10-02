<?php

declare(strict_types=1);

namespace CoreX\Config\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/**
 * A runtime requirement of the configuration (secret of an enabled feature, dangerous mode).
 *
 * Checks run before a process accepts work, never while building an image: the Options shape
 * layer stays valid on an empty environment. Packages tag their implementations with this contract.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RuntimeConfigCheck
{
    /** @return list<string> "<config path>: <message>"; empty when the process may accept work */
    public function violations(bool $production): array;
}
