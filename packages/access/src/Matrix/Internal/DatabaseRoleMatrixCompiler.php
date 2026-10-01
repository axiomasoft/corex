<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Internal;

use AzGuard\Models\RolePermission;
use CoreX\Access\Contracts\RoleMatrixCompiler;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Laravel\AzGuardRoleModel;
use CoreX\Access\Roles\Data\RoleMatrixEntry;
use CoreX\Access\Roles\Laravel\RoleMatrixData;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * `aut_role_matrix` → AzGuard `az_guard_role_permissions` (D100 key grammar
 * `{panel}.{entity_key}.{action}`) + scope-map version bump. Transactional
 * (B-11 §7.3 п.8): the AzGuard-role link and every permission-key row for
 * this role/panel are written in one transaction; the version bumps only
 * after it commits — a failure mid-compile leaves neither new keys nor a
 * new version.
 */
final readonly class DatabaseRoleMatrixCompiler implements RoleMatrixCompiler
{
    /**
     * `$activeEntities` defaults to null (resolved from the container
     * lazily, MatrixValidator precedent) so the pre-existing two-argument
     * `new DatabaseRoleMatrixCompiler($scopeMapVersion)` call sites (P2.8/P2.9)
     * keep working unchanged; DI containers still inject the real
     * ActiveEntityFilter via the constructor's type-hint.
     */
    public function __construct(
        private ScopeMapVersion $scopeMapVersion,
        private AuthOptions $options,
        private ?ActiveEntityFilter $activeEntities = null,
    ) {}

    #[Override]
    public function compile(string $roleId): void
    {
        $panel = $this->options->panel;

        DB::transaction(function () use ($roleId, $panel): void {
            $role = RoleMatrixData::role($roleId);

            $azguardRole = AzGuardRoleModel::query()->firstOrCreate(['name' => $role->slug]);

            if ($role->azguardRoleId !== $azguardRole->id) {
                RoleMatrixData::bindAzGuardRole($roleId, $azguardRole->id);
            }

            RolePermission::query()
                ->where('role_id', $azguardRole->id)
                ->where('panel_id', $panel)
                ->delete();

            $keys = collect(RoleMatrixData::matrix($role->id))
                ->filter(static fn (RoleMatrixEntry $row): bool => $row->scope !== Scope::None)
                ->filter(fn (RoleMatrixEntry $row): bool => $this->activeEntities()->isActive($row->entityKey))
                ->map(static fn (RoleMatrixEntry $row): string => "{$panel}.{$row->entityKey}.{$row->action}")
                ->unique();

            foreach ($keys as $key) {
                RolePermission::query()->create([
                    'role_id' => $azguardRole->id,
                    'permission_key' => $key,
                    'panel_id' => $panel,
                ]);
            }
            DB::afterCommit($this->scopeMapVersion->invalidator($roleId));
        });
    }

    #[Override]
    public function recompileAll(): void
    {
        collect(RoleMatrixData::roleIds())
            ->each(fn (string $roleId) => $this->compile($roleId));
    }

    private function activeEntities(): ActiveEntityFilter
    {
        return $this->activeEntities ?? app(ActiveEntityFilter::class);
    }
}
