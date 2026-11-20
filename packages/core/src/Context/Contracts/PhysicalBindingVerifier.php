<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface PhysicalBindingVerifier
{
    /** Verify the physical owner's marker on the supplied live connection. */
    public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void;
}
