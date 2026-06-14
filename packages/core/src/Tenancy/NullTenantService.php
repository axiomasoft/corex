<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Contracts\Tenant;
use CoreX\Contracts\TenantService;

/**
 * Default tenant service for single-tenant / on-premise installs: there is no
 * tenant, so the TenantScope stays inert (ADR-004).
 */
final class NullTenantService implements TenantService
{
    public function hasTenant(): bool
    {
        return false;
    }

    public function current(): ?Tenant
    {
        return null;
    }

    public function currentKey(): ?string
    {
        return null;
    }

    public function setCurrent(Tenant $tenant): void
    {
        // No-op: on-premise has no tenant boundary.
    }

    public function forget(): void
    {
        // No-op.
    }
}
