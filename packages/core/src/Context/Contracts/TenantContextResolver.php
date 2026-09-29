<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\TenantContext;

/**
 * Resolves the active TenantContext. Real (P2) implementations live in
 * axiomasoft/corex-tenancy; boxed installs default to SingleAccountResolver.
 */
interface TenantContextResolver
{
    public function current(): TenantContext;
}
