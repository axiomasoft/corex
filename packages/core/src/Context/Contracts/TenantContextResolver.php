<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;

/**
 * Resolves the active TenantContext. Real (P2) implementations live in
 * axiomasoft/corex-tenancy; boxed installs default to SingleAccountResolver.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenantContextResolver
{
    public function current(): TenantContext;
}
