<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface DataScopeFactory
{
    public function forCurrentPrincipal(TenantContext $context): DataScope;
}
