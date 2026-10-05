<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Internal\Laravel;

use AzGuard\Contracts\ContextGuard;
use AzGuard\Contracts\PermissionLayer;
use CoreX\AccessAzguard\Internal\AccessAzguardOptions;
use CoreX\AccessAzguard\Internal\TenantContextGuard;
use CoreX\AccessAzguard\Internal\WorkspacePermissionLayer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

/** @internal Bridge configuration composition. */
final class AccessAzguardBindingsRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(AccessAzguardOptions::class, static fn (Application $app): AccessAzguardOptions => AccessAzguardOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-access-azguard', []),
        ));

        // Apply registration-time pins without resolving the shared snapshot before boot.
        $config = $app->make(Repository::class);
        $options = AccessAzguardOptions::fromArray((array) $config->get('corex-access-azguard', []));
        $config->set('az-guard.table_names.roles', $options->rolesTable);
        $config->set('az-guard.column_names.morph_type', $options->morphType);
        $config->set('az-guard.models.role', $options->roleModel);

        $app->singleton(ContextGuard::class, TenantContextGuard::class);

        if ($options->bindPermissionLayer) {
            $app->bind(PermissionLayer::class, WorkspacePermissionLayer::class);
        }
    }
}
