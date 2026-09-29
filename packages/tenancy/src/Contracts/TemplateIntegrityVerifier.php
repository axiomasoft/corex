<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\QualifiedTemplate;

interface TemplateIntegrityVerifier
{
    public function qualify(ClaimRequest $request): QualifiedTemplate;
}
