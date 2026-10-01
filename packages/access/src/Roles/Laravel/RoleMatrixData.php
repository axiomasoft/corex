<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Laravel;

use CoreX\Access\Roles\Data\RoleMatrixEntry;
use CoreX\Access\Roles\Data\RoleView;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/** Eloquent storage adapter for matrix compilation; rows leave their owner as scalar views. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RoleMatrixData
{
    public static function role(string $roleId): RoleView
    {
        $role = Role::query()->findOrFail($roleId);

        return new RoleView($role->id, $role->slug, $role->assignable_to, $role->azguard_role_id);
    }

    /** @return list<RoleMatrixEntry> */
    public static function matrix(?string $roleId = null): array
    {
        return array_values(RoleMatrixRow::query()
            ->when($roleId !== null, static fn ($query) => $query->where('role_id', $roleId))
            ->get(['entity_key', 'action', 'scope'])
            ->map(static fn (RoleMatrixRow $row): RoleMatrixEntry => new RoleMatrixEntry($row->entity_key, $row->action, $row->scope))
            ->all());
    }

    /** @return list<string> */
    public static function roleIds(): array
    {
        return array_values(Role::query()->get(['id'])->map(static fn (Role $role): string => $role->id)->all());
    }

    public static function bindAzGuardRole(string $roleId, int $azguardRoleId): void
    {
        $role = Role::query()->findOrFail($roleId);
        $role->forceFill(['azguard_role_id' => $azguardRoleId]);
        Role::withSystemMutationAllowed(fn (): bool => $role->save());
    }
}
