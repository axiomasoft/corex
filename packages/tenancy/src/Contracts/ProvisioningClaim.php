<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\ClaimResult;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ProvisioningClaim
{
    public function claim(ClaimRequest $request): ClaimResult;
}
