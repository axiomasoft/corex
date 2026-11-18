<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\QualifiedTemplate;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TemplateIntegrityVerifier
{
    public function qualify(ClaimRequest $request): QualifiedTemplate;
}
