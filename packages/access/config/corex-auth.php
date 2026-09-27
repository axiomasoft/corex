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
    | Workspace scope class (D109)
    |--------------------------------------------------------------------------
    | Class WorkspaceScopeWiring::wire() registers as a global scope on every
    | Capability::WorkspaceScoped model, and Access\ScopePredicate lifts via
    | withoutGlobalScope() for scope `account` — a STRING, never an imported
    | class reference: corex/access does not depend on corex/tenancy (AC-28),
    | so CoreX\Tenancy\Scopes\WorkspaceScope is not necessarily loadable.
    | null = there is no workspace scope to register or lift (boxed profiles
    | without corex/tenancy).
    */
    'workspace_scope_class' => 'CoreX\\Tenancy\\Scopes\\WorkspaceScope',

    /*
    |--------------------------------------------------------------------------
    | AzGuard pins (D104)
    |--------------------------------------------------------------------------
    | corex/access does not fork AzGuard (D15) — these values are applied onto
    | AzGuard's own config at boot (CoreAuthServiceProvider) instead.
    | table_names.roles must read "az_guard_roles" to match DB_SCHEMA §3.5;
    | column_names.morph_type must be "uuid" for CoreX's uuid-keyed
    | principals (ADR-003) — direct_grants.grantable_id otherwise rejects a
    | uuid principal.
    */
    /*
    |--------------------------------------------------------------------------
    | Local login lane (D125 — OQ-18 variant A)
    |--------------------------------------------------------------------------
    | `Identity\PrincipalUserProvider` reads this table via query-builder and
    | returns a PrincipalIdentity(user, <id>) — the shared seam BOTH login
    | lanes construct through (parity, premortem C4): the OIDC lane
    | (corex/tenancy) ends in `Auth::loginUsingId($sub)`, which resolves
    | through this exact provider. `connection`/`table` name a table by
    | STRING, never an imported model — corex/access does not depend on
    | corex/tenancy (AC-28), so a boxed profile without it points `table` at
    | its own users table instead. `enabled=false` skips registering the
    | `corex-principal` auth driver entirely.
    */
    'identity' => [
        'enabled' => (bool) env('COREX_AUTH_IDENTITY_ENABLED', true),
        'connection' => env('COREX_AUTH_IDENTITY_CONNECTION'),
        'table' => env('COREX_AUTH_IDENTITY_TABLE', 'users'),
        'columns' => [
            'id' => 'id',
            'email' => 'email',
            'password' => 'password_hash',
            'active' => 'is_active',
            'deleted_at' => 'deleted_at',
        ],
    ],

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
