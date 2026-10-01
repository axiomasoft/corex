<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface TemplateIntegrityChecks
{
    public function verifyCurrent(int $version, ?string $clusterId = null): void;

    public function verifyAccount(TemplateAccountView $account): void;
}
