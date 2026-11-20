<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Contracts;

use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Tenant\Data\Verification\VerifiedBinding;

/** The external owner correlates the admitted generation and epoch with its current route and recovery anchor. */
#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface TenantBindingAuthority
{
    public function verify(PhysicalBinding $binding, TenantContext $context): VerifiedBinding;
}
