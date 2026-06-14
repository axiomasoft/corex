<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Contracts\Tenant;
use CoreX\Contracts\TenantService;

/**
 * Request/process-scoped tenant holder for multi-tenant products. Bind this
 * (via config('corex.tenancy.service')) to activate the TenantScope.
 */
final class ContainerTenantService implements TenantService
{
    private ?Tenant $tenant = null;

    public function hasTenant(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function currentKey(): ?string
    {
        return $this->tenant?->getTenantKey();
    }

    public function setCurrent(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }
}
