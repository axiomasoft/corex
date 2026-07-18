<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\ProvisionParams;

/**
 * Creates an account's tenant database (golden-template clone + post-steps).
 * Idempotent — a repeated call for an already-provisioned account is a no-op
 * (replayable pipeline, premortem B4). `drop()`/`export()` are lifecycle
 * operations owned by the P2.12 `AccountLifecycle`/`TenantDatabaseLifecycle`
 * contracts, not this one (Code Guidance P2.3).
 */
interface TenantDatabaseProvisioner
{
    public function provision(ProvisionParams $params): void;
}
