<?php

declare(strict_types=1);

namespace Flex\Modules\Contracts;

use CoreX\Tenancy\TenantContext;
use Flex\Modules\Enums\Capability;
use Flex\Modules\Exceptions\EntityNotFound;
use Flex\Modules\Registry\EntityDefinition;

/**
 * Central registry of ecosystem entities (B-10 §3.4). Single source of the
 * polymorphic morph map (ADR-003) and the contract satellites (search,
 * audit, attributes, workflow, comments, documents) query via
 * byCapability() to wire themselves without knowing the owning module.
 */
interface EntityRegistry
{
    /** @throws EntityNotFound */
    public function get(string $handle): EntityDefinition;

    public function has(string $handle): bool;

    /** @return list<EntityDefinition> active for the tenant (owning module enabled) */
    public function all(?TenantContext $ctx = null): array;

    /** @return list<EntityDefinition> */
    public function byCapability(Capability $cap, ?TenantContext $ctx = null): array;

    /** @param class-string $modelClass */
    public function forModel(string $modelClass): ?EntityDefinition;
}
