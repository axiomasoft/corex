<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Internal;

use AzGuard\Configuration\Config as AzGuardConfig;
use AzGuard\Registry\Contracts\GrantSource;
use AzGuard\Registry\Values\PermissionSet;
use CoreX\Access\Contracts\Principal;
use CoreX\Access\Enums\PrincipalType;
use CoreX\Context\Contracts\TenancyManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use InvalidArgumentException;
use Override;
use Psr\Clock\ClockInterface;
use UnexpectedValueException;

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
        private readonly ConnectionResolverInterface $connections,
        private readonly ClockInterface $clock,
    ) {}

    #[Override]
    public function permissionsFor(Authenticatable $user, string $panelId): PermissionSet
    {
        if ($user instanceof Principal) {
            $principalType = $user->principalType();
            $principalId = $user->principalId();
        } else {
            $identifier = $user->getAuthIdentifier();

            if (! is_string($identifier) && ! is_int($identifier)) {
                throw new InvalidArgumentException('AzGuard principal identifier must be a string or integer.');
            }

            $principalType = PrincipalType::User;
            $principalId = (string) $identifier;
        }

        $workspaceId = $this->tenancyManager->context()?->workspace?->id;

        if ($this->hasActiveOwnerAssignment(principalType: $principalType, principalId: $principalId, workspaceId: $workspaceId)) {
            return PermissionSet::wildcard();
        }

        $keys = $this->assignmentsQuery(principalType: $principalType, principalId: $principalId, workspaceId: $workspaceId)
            ->join('aut_azguard_role_links as links', 'links.role_id', '=', 'aut_role_assignments.role_id')
            ->join('aut_roles', 'aut_roles.id', '=', 'links.role_id')
            ->join(AzGuardConfig::rolePermissionsTable().' as azgrp', static function (JoinClause $join) use ($panelId): void {
                $join->on('azgrp.role_id', '=', 'links.azguard_role_id')
                    ->where('azgrp.panel_id', '=', $panelId);
            })
            ->whereNull('aut_roles.deleted_at')
            ->pluck('azgrp.permission_key')
            ->all();

        foreach ($keys as $key) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('AzGuard permission key must be a string.');
            }
        }

        return PermissionSet::fromRawKeys(array_values(array_unique($keys)));
    }

    #[Override]
    public function priority(): int
    {
        return 85;
    }

    private function hasActiveOwnerAssignment(PrincipalType $principalType, string $principalId, ?string $workspaceId): bool
    {
        return $this->assignmentsQuery(principalType: $principalType, principalId: $principalId, workspaceId: $workspaceId)
            ->join('aut_roles', 'aut_roles.id', '=', 'aut_role_assignments.role_id')
            ->where('aut_roles.slug', 'owner')
            ->where('aut_roles.is_system', true)
            ->whereNull('aut_roles.deleted_at')
            ->exists();
    }

    private function assignmentsQuery(PrincipalType $principalType, string $principalId, ?string $workspaceId): Builder
    {
        $now = $this->clock->now();

        return $this->connections->connection()->table('aut_role_assignments')
            ->where('aut_role_assignments.principal_type', $principalType->value)
            ->where('aut_role_assignments.principal_id', $principalId)
            ->where(static function (Builder $q) use ($now): void {
                $q->whereNull('aut_role_assignments.expires_at')
                    ->orWhere('aut_role_assignments.expires_at', '>', $now);
            })
            ->where(static function (Builder $q) use ($workspaceId): void {
                $q->whereNull('aut_role_assignments.workspace_id');

                if ($workspaceId !== null) {
                    $q->orWhere('aut_role_assignments.workspace_id', $workspaceId);
                }
            });
    }
}
