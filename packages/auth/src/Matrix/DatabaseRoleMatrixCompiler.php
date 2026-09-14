<?php

declare(strict_types=1);

namespace CoreX\Auth\Matrix;

use AzGuard\Models\RolePermission;
use CoreX\Auth\Contracts\RoleMatrixCompiler as RoleMatrixCompilerContract;
use CoreX\Auth\Enums\Scope;
use CoreX\Auth\Models\Role;
use CoreX\Auth\Models\RoleMatrixRow;
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
final readonly class DatabaseRoleMatrixCompiler implements RoleMatrixCompilerContract
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
        private ?ActiveEntityFilter $activeEntities = null,
    ) {}

    #[Override]
    public function compile(string $roleId): void
    {
        $panel = (string) config('corex-auth.panel', 'app');

        DB::transaction(function () use ($roleId, $panel): void {
            $role = Role::query()->findOrFail($roleId);

            $azguardRole = AzGuardRoleModel::query()->firstOrCreate(['name' => $role->slug]);

            if ($role->azguard_role_id !== $azguardRole->id) {
                $role->forceFill(['azguard_role_id' => $azguardRole->id]);
                Role::withSystemMutationAllowed(fn (): bool => $role->save());
            }

            RolePermission::query()
                ->where('role_id', $azguardRole->id)
                ->where('panel_id', $panel)
                ->delete();

            $keys = RoleMatrixRow::query()
                ->where('role_id', $role->id)
                ->where('scope', '!=', Scope::None->value)
                ->get(['entity_key', 'action'])
                ->filter(fn (RoleMatrixRow $row): bool => $this->activeEntities()->isActive($row->entity_key))
                ->map(static fn (RoleMatrixRow $row): string => "{$panel}.{$row->entity_key}.{$row->action}")
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
        Role::query()
            ->whereNull('deleted_at')
            ->pluck('id')
            ->each(fn (string $roleId) => $this->compile($roleId));
    }

    private function activeEntities(): ActiveEntityFilter
    {
        return $this->activeEntities ?? app(ActiveEntityFilter::class);
    }
}
