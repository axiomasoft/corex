<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Contracts;

use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface TemplateIntegrityChecks
{
    public function verifyCurrent(int $version, ?string $clusterId = null): void;

    public function verifyAccount(TemplateAccountView $account): void;
}
