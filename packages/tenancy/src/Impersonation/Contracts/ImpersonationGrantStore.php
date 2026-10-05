<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Impersonation\Data\ClaimedGrant;
use CoreX\Tenancy\Impersonation\Data\GrantClaim;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ImpersonationGrantStore
{
    public function claim(GrantClaim $claim): ?ClaimedGrant;

    public function claimedReason(string $grantId): ?string;
}
