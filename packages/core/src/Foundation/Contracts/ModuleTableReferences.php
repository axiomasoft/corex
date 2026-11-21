<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** A reference owner whose complete SQL inventory belongs to the native module's tenant schema. */
#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ModuleTableReferences extends ModuleReferences
{
    /** @return non-empty-list<string> Unqualified tables on the verified physical module connection. */
    public function moduleTables(): array;
}
