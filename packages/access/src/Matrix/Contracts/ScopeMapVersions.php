<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface ScopeMapVersions
{
    public function current(string $roleId): int;
}
