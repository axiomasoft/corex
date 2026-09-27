<?php

declare(strict_types=1);

namespace Flex\Crm\Data;

use CoreX\Tenancy\TenantContext;

final readonly class ExecutionContext
{
    public function __construct(
        public TenantContext $tenant,
        public string $connectionName,
        public Actor $actor,
        public string|int $principalId,
        public bool $allowAccountLevel = false,
    ) {}
}
