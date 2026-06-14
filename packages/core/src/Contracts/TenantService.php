<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Holds the current tenant for the request/process lifetime.
 *
 * The mere binding of a non-null implementation in the container activates
 * the TenantScope on tenant-owned models. On-premise installs leave the
 * NullTenantService bound and run unscoped (ADR-004).
 */
interface TenantService
{
    public function hasTenant(): bool;

    public function current(): ?Tenant;

    public function currentKey(): ?string;

    public function setCurrent(Tenant $tenant): void;

    public function forget(): void;
}
