<?php

declare(strict_types=1);
use CoreX\Auth\Matrix\AzGuardRoleModel;

return [

    /*
    |--------------------------------------------------------------------------
    | Panel
    |--------------------------------------------------------------------------
    | First segment of every compiled AzGuard permission key (D100):
    | "{panel}.{entity_key}.{action}". One UI-matrix per account — the panel
    | is an interface plane (app/admin), not a module.
    */
    'panel' => env('COREX_AUTH_PANEL', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Scope-map cache store
    |--------------------------------------------------------------------------
    | Cache store ScopeMapVersion reads/writes. null = the app's default
    | store. A persistent store name here must also be listed in
    | tenancy.cache.stores (findings/P2.8-azguard-inventory-2026-07-26.md §5)
    | for tenant isolation — this is checked, never assumed.
    */
    'cache' => [
        'store' => env('COREX_AUTH_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace permission-layer binding (D101)
    |--------------------------------------------------------------------------
    | WorkspacePermissionLayer discriminates AzGuard's permission cache by the
    | active workspace so two workspaces never share a cached permission set
    | (findings/P2.8-azguard-inventory-2026-07-26.md §6). AzGuard's
    | PermissionLayer slot is singular — set this false when another package
    | (e.g. azguard/context) binds its own layer, so this one steps aside
    | instead of conflicting.
    */
    'bind_permission_layer' => env('COREX_AUTH_BIND_PERMISSION_LAYER', true),

    /*
    |--------------------------------------------------------------------------
    | AzGuard pins (D104)
    |--------------------------------------------------------------------------
    | corex/auth does not fork AzGuard (D15) — these values are applied onto
    | AzGuard's own config at boot (CoreAuthServiceProvider) instead.
    | table_names.roles must read "az_guard_roles" to match DB_SCHEMA §3.5;
    | column_names.morph_type must be "uuid" for CoreX's uuid-keyed
    | principals (ADR-003) — direct_grants.grantable_id otherwise rejects a
    | uuid principal.
    */
    'azguard_pins' => [
        'table_names' => [
            'roles' => 'az_guard_roles',
        ],
        'column_names' => [
            'morph_type' => 'uuid',
        ],
        // AzGuard's own Role model does not read table_names.roles itself
        // (only RolePermission does) — this seam (config/az-guard.php
        // "Models") swaps in a subclass that does, so the table_names pin
        // above actually resolves. See CoreX\Auth\Matrix\AzGuardRoleModel.
        'models' => [
            'role' => AzGuardRoleModel::class,
        ],
    ],

];
