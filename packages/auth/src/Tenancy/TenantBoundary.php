<?php

declare(strict_types=1);

namespace CoreX\Auth\Tenancy;

use CoreX\Concerns\BelongsToTenant;
use CoreX\Contracts\TenantService;

/**
 * Read-only tenant-boundary check (the CheckTenantBoundary stage of ADR-009):
 * a tenant-owned resource is only permitted when it belongs to the active
 * tenant. Non-tenant resources and the unbound (on-premise/console) case are
 * always permitted. Use it in policies as defence-in-depth on top of
 * corex/core's query-time TenantScope.
 */
final class TenantBoundary
{
    public function __construct(
        private readonly TenantService $tenants,
    ) {}

    public function permits(object $resource): bool
    {
        if (! in_array(BelongsToTenant::class, class_uses_recursive($resource), true)) {
            return true;
        }

        if (! $this->tenants->hasTenant()) {
            return true;
        }

        $resolveKey = [$resource, 'tenantKey'];
        $resourceTenant = is_callable($resolveKey) ? $resolveKey() : null;

        return $resourceTenant === null || $resourceTenant === $this->tenants->currentKey();
    }
}
