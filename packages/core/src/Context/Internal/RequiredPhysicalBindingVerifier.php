<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeAuthorityUnavailable;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

#[Internal]
final readonly class RequiredPhysicalBindingVerifier implements PhysicalBindingVerifier
{
    public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        throw new ScopeAuthorityUnavailable('The host must bind a physical owner verifier for scoped work.');
    }
}
