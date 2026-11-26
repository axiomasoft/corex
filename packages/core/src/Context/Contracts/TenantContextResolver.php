<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\TenantContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Resolves the active TenantContext. Cloud implementations live in
 * axiomasoft/corex-tenancy; boxed installs default to SingleAccountResolver.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenantContextResolver
{
    public function current(): TenantContext;
}
