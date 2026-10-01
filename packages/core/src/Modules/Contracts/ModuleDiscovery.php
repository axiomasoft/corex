<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Data\ModuleManifest;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ModuleDiscovery
{
    /** @return list<ModuleManifest> */
    public function discover(): array;
}
