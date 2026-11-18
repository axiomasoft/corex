<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Data;

use CoreX\Context\Data\TenantContext;
use Packman\Attributes\Internal;

#[Internal]
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
