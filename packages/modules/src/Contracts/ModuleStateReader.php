<?php

declare(strict_types=1);

namespace Flex\Modules\Contracts;

use CoreX\Tenancy\TenantContext;

/**
 * Seam separating {@see ModuleRegistry}'s hot path from the database
 * (D12: this package stays PG-free until P1.6). Returns the enabled set for
 * one tenant, keyed by module composer name.
 */
interface ModuleStateReader
{
    /** @return array<string, array{state: string, edition: string}> */
    public function enabledFor(TenantContext $ctx): array;
}
