<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;

/**
 * Seam separating {@see ModuleRegistry}'s hot path from the database (this
 * package stays PG-free). Returns the enabled set for one tenant, keyed by
 * module composer name.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ModuleStateReader
{
    /** @return array<string, array{state: string, edition: string}> */
    public function enabledFor(TenantContext $ctx): array;
}
