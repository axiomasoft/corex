<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface TaskModuleContext
{
    public function context(): TenantContext;
}
