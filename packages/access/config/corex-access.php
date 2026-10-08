<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Panel
    |--------------------------------------------------------------------------
    | First segment of every permission key:
    | "{panel}.{entity_key}.{action}". One UI-matrix per account — the panel
    | is an interface plane (app/admin), not a module.
    */
    'panel' => env('COREX_ACCESS_PANEL', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Scope-map cache store
    |--------------------------------------------------------------------------
    | Cache store ScopeMapVersion reads/writes. null = the app's default
    | store. A persistent store name here must also be listed in
    | tenancy.cache.stores
    | for tenant isolation — this is checked, never assumed.
    */
    'cache' => [
        'store' => env('COREX_ACCESS_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace scope class override (D109)
    |--------------------------------------------------------------------------
    | null (default) = use the scope named by the kernel
    | CoreX\Context\Laravel\WorkspaceScopeProvider, which axiomasoft/corex-tenancy
    | binds; no binding = boxed profile, explicitly no workspace scope (AC-28).
    | A class set here overrides the provider and MUST exist — a missing
    | class fails at boot instead of silently leaving models unscoped.
    | WorkspaceScopeWiring registers it on every Capability::WorkspaceScoped
    | model; Access\ScopePredicate lifts it for scope `account`.
    */
    'workspace_scope_class' => null,

    /*
    |--------------------------------------------------------------------------
    | Local login lane (D125 — OQ-18 variant A)
    |--------------------------------------------------------------------------
    | `Identity\PrincipalUserProvider` reads this table via query-builder and
    | returns a PrincipalIdentity(user, <id>) — the shared seam BOTH login
    | lanes construct through (parity, premortem C4): the OIDC lane
    | (axiomasoft/corex-tenancy) ends in `Auth::loginUsingId($sub)`, which resolves
    | through this exact provider. `connection`/`table` name a table by
    | STRING, never an imported model — axiomasoft/corex-access does not depend on
    | axiomasoft/corex-tenancy (AC-28), so a boxed profile without it points `table` at
    | its own users table instead. `enabled=false` skips registering the
    | `corex-principal` auth driver entirely.
    */
    'identity' => [
        'enabled' => (bool) env('COREX_ACCESS_IDENTITY_ENABLED', true),
        'connection' => env('COREX_ACCESS_IDENTITY_CONNECTION'),
        'table' => env('COREX_ACCESS_IDENTITY_TABLE', 'users'),
        'columns' => [
            'id' => 'id',
            'email' => 'email',
            'password' => 'password_hash',
            'active' => 'is_active',
            'deleted_at' => 'deleted_at',
        ],
    ],

];
