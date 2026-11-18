<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Contracts;

use CoreX\Tenancy\Impersonation\Data\ClaimedGrant;
use CoreX\Tenancy\Impersonation\Data\GrantClaim;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ImpersonationGrantStore
{
    public function claim(GrantClaim $claim): ?ClaimedGrant;

    public function claimedReason(string $grantId): ?string;
}
