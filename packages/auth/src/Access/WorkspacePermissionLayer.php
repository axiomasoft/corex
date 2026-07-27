<?php

declare(strict_types=1);

namespace CoreX\Auth\Access;

use AzGuard\Contracts\PermissionLayer;
use AzGuard\Registry\Values\PermissionSet;
use CoreX\Tenancy\Contracts\TenancyManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Override;

/**
 * Cache-discriminator layer (D101) — apply() is the identity function
 * (AutMatrixGrantSource already scoped its keys to the active workspace at
 * the source, so this layer never narrows); its role is purely to tag the
 * resolver's per-request cache entry so workspace 42 and workspace 99 never
 * share one (findings/P2.8-azguard-inventory-2026-07-26.md §6) — without
 * it, a resolved set for one workspace would leak into the next request-
 * local lookup for another.
 */
final readonly class WorkspacePermissionLayer implements PermissionLayer
{
    public function __construct(
        private TenancyManager $tenancyManager,
    ) {}

    #[Override]
    public function apply(PermissionSet $global, Authenticatable $user, string $panelId): PermissionSet
    {
        return $global;
    }

    #[Override]
    public function cacheDiscriminator(string $panelId): string
    {
        return $this->tenancyManager->context()?->workspace->id ?? '';
    }
}
