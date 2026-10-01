<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\ProvisionParams;

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
