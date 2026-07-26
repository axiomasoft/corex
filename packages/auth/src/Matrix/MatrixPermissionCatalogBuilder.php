<?php

declare(strict_types=1);

namespace CoreX\Auth\Matrix;

use AzGuard\Registry\Contracts\PermissionCatalogBuilder;
use AzGuard\Registry\Contracts\PermissionDefinition;
use AzGuard\Registry\Definitions\SimplePermissionDefinition;
use AzGuard\Registry\Resolver\EffectivePermissionResolver;
use CoreX\Auth\Enums\Scope;
use CoreX\Auth\Models\RoleMatrixRow;
use Override;

/**
 * Feeds the keys compiled from `aut_role_matrix` into AzGuard's permission
 * catalog (`registerCatalogBuilder()`) — without it, a panel with a
 * registered catalog would have every compiled key filtered out by
 * {@see EffectivePermissionResolver}, and a role
 * with `validate_role_permissions` enabled could never save one
 * (findings/P2.8-azguard-inventory-2026-07-26.md §7).
 */
final readonly class MatrixPermissionCatalogBuilder implements PermissionCatalogBuilder
{
    #[Override]
    public function supports(string $panelId): bool
    {
        return $panelId === (string) config('corex-auth.panel', 'app');
    }

    /** @return list<PermissionDefinition> */
    #[Override]
    public function build(string $panelId): array
    {
        if (! $this->supports($panelId)) {
            return [];
        }

        return RoleMatrixRow::query()
            ->where('scope', '!=', Scope::None->value)
            ->get(['entity_key', 'action'])
            ->map(static fn (RoleMatrixRow $row): string => "{$panelId}.{$row->entity_key}.{$row->action}")
            ->unique()
            ->map(static fn (string $key): PermissionDefinition => new SimplePermissionDefinition($key, $panelId))
            ->values()
            ->all();
    }
}
