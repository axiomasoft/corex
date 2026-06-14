<?php

declare(strict_types=1);

namespace CoreX\Crm\Tests\Stubs;

use CoreX\Contracts\Tenant as TenantContract;

final class Tenant implements TenantContract
{
    public function __construct(
        private readonly string $key,
    ) {}

    public function getTenantKey(): string
    {
        return $this->key;
    }
}
