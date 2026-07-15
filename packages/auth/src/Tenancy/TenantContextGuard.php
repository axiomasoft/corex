<?php

declare(strict_types=1);

namespace CoreX\Auth\Tenancy;

use AzGuard\Contracts\ContextGuard;
use Flex\Modules\Contracts\EntityRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Implements AzGuard's ContextGuard seam (the binding AzGuard expects from an
 * external package) so `$user->hasPermissionIn($type, $id, $permission)` works:
 * the context entity is resolved through flex/modules's EntityRegistry (single
 * morph source, ADR-003), and the check passes when the user has the
 * permission scoped to that entity, or globally within the panel.
 *
 * Tenant isolation of the resolved entity is enforced by corex/core's
 * TenantScope at query time; {@see TenantBoundary} covers already-loaded models.
 */
final class TenantContextGuard implements ContextGuard
{
    public function __construct(
        private readonly EntityRegistry $entities,
    ) {}

    #[Override]
    public function checkInContext(
        Authenticatable $user,
        string $contextType,
        int|string $contextId,
        string $permission,
        string $panelId,
    ): bool {
        $entity = $this->resolveContext($contextType, $contextId);

        if (! $entity instanceof Model) {
            return false;
        }

        $scopedCheck = [$user, 'hasScopedPermission'];

        if (is_callable($scopedCheck) && $scopedCheck($permission, $entity) === true) {
            return true;
        }

        $globalCheck = [$user, 'hasPermission'];

        return is_callable($globalCheck) && $globalCheck($permission, $panelId) === true;
    }

    private function resolveContext(string $contextType, int|string $contextId): ?Model
    {
        if (! $this->entities->has($contextType)) {
            return null;
        }

        $class = $this->entities->get($contextType)->model;
        $instance = new $class;

        if (! $instance instanceof Model) {
            return null;
        }

        return $instance->newQuery()->whereKey($contextId)->first();
    }
}
