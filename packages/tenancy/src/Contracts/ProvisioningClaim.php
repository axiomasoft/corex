<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\ClaimResult;

interface ProvisioningClaim
{
    public function claim(ClaimRequest $request): ClaimResult;
}
