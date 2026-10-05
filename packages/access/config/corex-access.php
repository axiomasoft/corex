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
    | Workspace scope class (D109)
    |--------------------------------------------------------------------------
    | Class WorkspaceScopeWiring::wire() registers as a global scope on every
    | Capability::WorkspaceScoped model, and Access\ScopePredicate lifts via
    | withoutGlobalScope() for scope `account` — a STRING, never an imported
    | class reference: axiomasoft/corex-access does not depend on axiomasoft/corex-tenancy (AC-28),
    | so CoreX\Tenancy\Laravel\Scopes\WorkspaceScope is not necessarily loadable.
    | null = there is no workspace scope to register or lift (boxed profiles
    | without axiomasoft/corex-tenancy).
    */
    'workspace_scope_class' => 'CoreX\\Tenancy\\Laravel\\Scopes\\WorkspaceScope',

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
