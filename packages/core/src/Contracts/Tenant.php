<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * A tenant in multi-tenant mode. The product supplies the concrete model
 * (e.g. FlexCRM's Workspace); the kernel treats the key as opaque.
 */
interface Tenant
{
    public function getTenantKey(): string;
}
