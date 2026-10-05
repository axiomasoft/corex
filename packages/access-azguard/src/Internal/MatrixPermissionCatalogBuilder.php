<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Internal;

use AzGuard\Registry\Contracts\PermissionCatalogBuilder;
use AzGuard\Registry\Contracts\PermissionDefinition;
use AzGuard\Registry\Definitions\SimplePermissionDefinition;
use AzGuard\Registry\Resolver\EffectivePermissionResolver;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Access\Roles\Data\RoleMatrixEntry;
use CoreX\Access\Roles\Laravel\RoleMatrixData;
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
    /**
     * `$activeEntities` defaults to null (resolved from the container
     * Catalog filtering follows the public access entity activity port.
     */
    public function __construct(
        private AuthOptions $options,
        private EntityActivity $activeEntities,
    ) {}

    #[Override]
    public function supports(string $panelId): bool
    {
        return $panelId === $this->options->panel;
    }

    /** @return list<PermissionDefinition> */
    #[Override]
    public function build(string $panelId): array
    {
        if (! $this->supports($panelId)) {
            return [];
        }

        return array_values(collect(RoleMatrixData::matrix())
            ->filter(static fn (RoleMatrixEntry $row): bool => $row->scope !== Scope::None)
            ->filter(fn (RoleMatrixEntry $row): bool => $this->activeEntities->isActive($row->entityKey))
            ->map(static fn (RoleMatrixEntry $row): string => "{$panelId}.{$row->entityKey}.{$row->action}")
            ->unique()
            ->map(static fn (string $key): PermissionDefinition => new SimplePermissionDefinition($key, $panelId))
            ->values()
            ->all());
    }
}
