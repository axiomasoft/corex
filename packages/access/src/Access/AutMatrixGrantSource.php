<?php

declare(strict_types=1);

namespace CoreX\Auth\Access;

use AzGuard\Registry\Contracts\GrantSource;
use AzGuard\Registry\Values\PermissionSet;
use AzGuard\Support\Config as AzGuardConfig;
use CoreX\Auth\Contracts\Principal;
use CoreX\Auth\Enums\PrincipalType;
use CoreX\Tenancy\Contracts\TenancyManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * GrantSource reading `aut_role_assignments` (D101) — the built-in
 * DatabaseRoleGrantSource has no workspace/TTL dimension (findings/P2.8-
 * azguard-inventory-2026-07-26.md §4), so this is a sibling source, not a
 * replacement. Priority 85, the built-in DatabaseRole=90's own docblock
 * slot for a custom source between it and DirectGrant=80.
 *
 * A principal with an active assignment of the system role `owner` gets
 * the wildcard (D103) — the account cannot be in a "nobody can do anything"
 * state before any module populates the matrix. The wildcard is computed
 * here, never stored as '*' in az_guard_role_permissions (the catalog
 * filter/validator would reject it).
 */
final readonly class AutMatrixGrantSource implements GrantSource
{
    public function __construct(
        private TenancyManager $tenancyManager,
    ) {}

    #[Override]
    public function permissionsFor(Authenticatable $user, string $panelId): PermissionSet
    {
        [$principalType, $principalId] = $user instanceof Principal
            ? [$user->principalType(), $user->principalId()]
            : [PrincipalType::User, (string) $user->getAuthIdentifier()];

        $workspaceId = $this->tenancyManager->context()?->workspace?->id;

        if ($this->hasActiveOwnerAssignment($principalType, $principalId, $workspaceId)) {
            return PermissionSet::wildcard();
        }

        $keys = $this->assignmentsQuery($principalType, $principalId, $workspaceId)
            ->join('aut_roles', 'aut_roles.id', '=', 'aut_role_assignments.role_id')
            ->join(AzGuardConfig::rolePermissionsTable().' as azgrp', static function ($join) use ($panelId): void {
                $join->on('azgrp.role_id', '=', 'aut_roles.azguard_role_id')
                    ->where('azgrp.panel_id', '=', $panelId);
            })
            ->whereNull('aut_roles.deleted_at')
            ->pluck('azgrp.permission_key')
            ->all();

        return PermissionSet::fromRawKeys(array_values(array_unique($keys)));
    }

    #[Override]
    public function priority(): int
    {
        return 85;
    }

    private function hasActiveOwnerAssignment(PrincipalType $principalType, string $principalId, ?string $workspaceId): bool
    {
        return $this->assignmentsQuery($principalType, $principalId, $workspaceId)
            ->join('aut_roles', 'aut_roles.id', '=', 'aut_role_assignments.role_id')
            ->where('aut_roles.slug', 'owner')
            ->where('aut_roles.is_system', true)
            ->whereNull('aut_roles.deleted_at')
            ->exists();
    }

    private function assignmentsQuery(PrincipalType $principalType, string $principalId, ?string $workspaceId): Builder
    {
        return DB::table('aut_role_assignments')
            ->where('aut_role_assignments.principal_type', $principalType->value)
            ->where('aut_role_assignments.principal_id', $principalId)
            ->where(static function (Builder $q): void {
                $q->whereNull('aut_role_assignments.expires_at')
                    ->orWhere('aut_role_assignments.expires_at', '>', now());
            })
            ->where(static function (Builder $q) use ($workspaceId): void {
                $q->whereNull('aut_role_assignments.workspace_id');

                if ($workspaceId !== null) {
                    $q->orWhere('aut_role_assignments.workspace_id', $workspaceId);
                }
            });
    }
}
