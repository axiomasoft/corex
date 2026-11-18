<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\ClaimResult;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ProvisioningClaim
{
    public function claim(ClaimRequest $request): ClaimResult;
}
