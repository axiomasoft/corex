<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Internal;

use AzGuard\Models\RolePermission;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Access\Matrix\Events\RoleMatrixCompiled;
use CoreX\Access\Roles\Data\RoleMatrixEntry;
use CoreX\Access\Roles\Laravel\RoleMatrixData;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use LogicException;

/** Mirrors a compiled native matrix into the optional AzGuard storage. */
final readonly class SyncAzGuardRolePermissions
{
    public function __construct(
        private AuthOptions $options,
        private EntityActivity $activeEntities,
        private RoleLinkStore $links,
        private ConnectionResolverInterface $connections,
    ) {}

    public function handle(RoleMatrixCompiled $event): void
    {
        $role = RoleMatrixData::role($event->roleId);
        $matrix = RoleMatrixData::matrix($role->id);
        $panel = $this->options->panel;
        $connection = $this->connections->connection();

        if (! $connection instanceof Connection) {
            throw new LogicException('AzGuard bridge requires a Laravel database connection.');
        }

        $connection->transaction(function () use ($role, $matrix, $panel, $connection): void {
            $azguardRole = (new AzGuardRoleModel)->setConnection($connection->getName())
                ->newQuery()->firstOrCreate(['name' => $role->slug]);

            $this->links->upsert($role->id, $azguardRole->id);

            $permissions = (new RolePermission)->setConnection($connection->getName());
            $permissions->newQuery()->where('role_id', $azguardRole->id)
                ->where('panel_id', $panel)->delete();

            $keys = collect($matrix)
                ->filter(static fn (RoleMatrixEntry $row): bool => $row->scope !== Scope::None)
                ->filter(fn (RoleMatrixEntry $row): bool => $this->activeEntities->isActive($row->entityKey))
                ->map(static fn (RoleMatrixEntry $row): string => "{$panel}.{$row->entityKey}.{$row->action}")
                ->unique();

            foreach ($keys as $key) {
                $permissions->newQuery()->create([
                    'role_id' => $azguardRole->id,
                    'permission_key' => $key,
                    'panel_id' => $panel,
                ]);
            }
        });
    }
}
