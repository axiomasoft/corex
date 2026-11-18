<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\ProvisionParams;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Creates an account's tenant database (golden-template clone + post-steps).
 * Idempotent — a repeated call for an already-provisioned account is a no-op
 * (replayable pipeline). `drop()`/`export()` are lifecycle operations owned
 * by the `AccountLifecycle`/`TenantDatabaseLifecycle` contracts, not this
 * one.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenantDatabaseProvisioner
{
    public function provision(ProvisionParams $params): void;
}
