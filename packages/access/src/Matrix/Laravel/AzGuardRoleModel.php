<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Laravel;

use AzGuard\Configuration\Config;
use AzGuard\Models\Role as AzGuardRole;
use AzGuard\Models\RolePermission;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Override;

/**
 * @corex-model-extension AzGuard role configuration adapter; preserves Role columns/relations and overrides only the configured roles table.
 * AzGuard's own `Role` model does not read `table_names.roles` itself (only
 * {@see RolePermission} does) — the config pin D104 sets
 * (`az_guard_roles`) would otherwise be inert. Bound as `az-guard.models.role`
 * (CoreAuthServiceProvider), AzGuard's own `Config::roleModel()` seam
 * (`config/az-guard.php` "Models" — "Replace with your own subclasses"),
 * never a fork of the package (D15).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AzGuardRoleModel extends AzGuardRole
{
    #[Override]
    public function getTable(): string
    {
        return Config::rolesTable();
    }
}
