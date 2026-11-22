<?php

declare(strict_types=1);

namespace App\ProductProof;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use Flex\Crm\Context\Contracts\CrmExecutionContext;
use Flex\Crm\Context\Data\Actor;
use Flex\Crm\Context\Data\ExecutionContext;
use Illuminate\Database\DatabaseManager;

final readonly class CrmActor implements CrmExecutionContext
{
    public function __construct(
        private TenantContextResolver $tenants,
        private WorkPrincipalAuthority $principals,
        private DatabaseManager $database,
    ) {}

    public function current(): ExecutionContext
    {
        $tenant = $this->tenants->current();
        $principal = $this->principals->current($tenant);

        return new ExecutionContext(
            tenant: $tenant,
            connectionName: $this->database->connection()->getName(),
            actor: new Actor(source: $principal->kind, id: $principal->id, name: 'Verified CRM fixture user'),
            principalId: $principal->id,
        );
    }
}
