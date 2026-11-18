<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Contracts;

use CoreX\Context\Data\TenantContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface TaskModuleContext
{
    public function context(): TenantContext;
}
