<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Access\Enums\Scope;
use CoreX\Access\Exceptions\ScopeCapabilityMismatch;
use CoreX\Access\Matrix\Contracts\EntityActivity as ActiveEntityFilter;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Permissions;

/**
 * Read-only reader of the declared permission surface — `(entity_key,
 * action, allowed scopes)` per active module's `Extend\Permissions`
 * declaration — for the UI role-matrix. Never writes `aut_role_matrix`
 * (that stays the module's own doing via the matrix seeder/merge
 * path); this class only tells the matrix editor which cells to show and
 * which scope options are legal for each.
 *
 * `isScopeable() === false` ⇒ the entity does not participate in the scope
 * axis at all, so `account` is the only legal value. Scopeable
 * entities get every {@see Scope} case normalized through
 * {@see ScopeAvailability} — a scope whose capability the entity never
 * declared collapses to `account` (or throws {@see ScopeCapabilityMismatch}
 * when the physical column exists without the declaration — a
 * genuine entity-registration bug this surface deliberately does not hide).
 *
 * @internal
 */
#[Internal]
final readonly class PermissionSurface
{
    public function __construct(
        private ModuleRegistry $modules,
        private ActiveEntityFilter $activeEntities,
        private ScopeAvailability $scopeAvailability,
    ) {}

    /** @return list<array{entity_key: string, action: string, scopes: list<Scope>}> */
    public function forActiveModules(): array
    {
        $rows = [];

        foreach ($this->modules->compiled()->permissions as $declaration) {
            if (! $this->activeEntities->isActive($declaration->entity)) {
                continue;
            }

            foreach ($declaration->actions as $action) {
                $rows[] = [
                    'entity_key' => $declaration->entity,
                    'action' => $action,
                    'scopes' => $this->availableScopes($declaration),
                ];
            }
        }

        return $rows;
    }

    /** @return list<Scope> */
    private function availableScopes(Permissions $declaration): array
    {
        if (! $declaration->isScopeable) {
            return [Scope::Account];
        }

        $scopes = [];

        foreach (Scope::cases() as $case) {
            $normalized = $this->scopeAvailability->normalize(entityKey: $declaration->entity, scope: $case);

            if (! in_array($normalized, $scopes, true)) {
                $scopes[] = $normalized;
            }
        }

        return $scopes;
    }
}
