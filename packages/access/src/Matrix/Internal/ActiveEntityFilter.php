<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Internal;

use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Context\Contracts\TenantContextResolver;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

/**
 * Single owner of "does this entity_key currently give out a permission
 * key" (AC-27, D112/D113): {@see DatabaseRoleMatrixCompiler} and
 * {@see MatrixPermissionCatalogBuilder} both filter through this one method
 * instead of repeating the two-part condition — unknown entity_key OR
 * inactive owning module — twice.
 *
 * `TenantContextResolver::current()` (not the nullable
 * `TenancyManager::context()`) — the same contract `DomainEvent` itself
 * resolves at construction (B-11 §3.1) — throws `CentralContextException`
 * in the true central/root scope instead of guessing a tenant. Both call
 * sites already assume a tenant-scoped DB connection (`aut_role_matrix` is
 * a tenant table), so a resolvable context is an existing precondition, not
 * a new one; boxed installs (`SingleAccountResolver`) never throw here.
 */
#[Internal]
final readonly class ActiveEntityFilter implements EntityActivity
{
    public function __construct(
        private EntityRegistry $entities,
        private ModuleRegistry $modules,
        private TenantContextResolver $tenantContext,
    ) {}

    public function isActive(string $entityKey): bool
    {
        if (! $this->entities->has($entityKey)) {
            return false;
        }

        $module = $this->entities->get($entityKey)->module;

        return $this->modules->isActive(name: $module, ctx: $this->tenantContext->current());
    }
}
