<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Laravel;

use CoreX\Access\Roles\Data\RoleMatrixEntry;
use CoreX\Access\Roles\Data\RoleView;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Eloquent storage adapter for matrix compilation; rows leave their owner as scalar views. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RoleMatrixData
{
    public static function role(string $roleId): RoleView
    {
        $role = Role::query()->findOrFail($roleId);

        return new RoleView(id: $role->id, slug: $role->slug, assignableTo: $role->assignable_to);
    }

    /** @return list<RoleMatrixEntry> */
    public static function matrix(?string $roleId = null): array
    {
        return array_values(RoleMatrixRow::query()
            ->when($roleId !== null, static fn ($query) => $query->where('role_id', $roleId))
            ->get(['entity_key', 'action', 'scope'])
            ->map(static fn (RoleMatrixRow $row): RoleMatrixEntry => new RoleMatrixEntry(entityKey: $row->entity_key, action: $row->action, scope: $row->scope))
            ->all());
    }

    /** @return list<string> */
    public static function roleIds(): array
    {
        return array_values(Role::query()->get(['id'])->map(static fn (Role $role): string => $role->id)->all());
    }
}
