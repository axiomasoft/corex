<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Central connection (B-11 §2.1 / D14)
    |--------------------------------------------------------------------------
    | root_* migrations and models target this connection — the control-plane
    | database, never a tenant/account database. Override per-host if the
    | central DB uses a different Laravel connection name.
    */
    'central_connection' => env('COREX_TENANCY_CENTRAL_CONNECTION', 'pgsql'),

    // Runtime facilities and template attestations belong to the external Tenant host.
    // Bind TenantRuntime, AccountIsolationMapper, AccountResources and the integrity ports.

    /*
    |--------------------------------------------------------------------------
    | Tenant content storage (P2.2)
    |--------------------------------------------------------------------------
    |
    | These roots are owned by ContextTenantStorage. `fake-s3` is
    | a local object-prefix test backend only: it has no SDK, credentials, or
    | claim of compatibility with a real provider.
    |
    */
    'storage' => [
        'durable_driver' => env('COREX_TENANCY_STORAGE_DURABLE_DRIVER', 'local'),
        'durable_root' => env('COREX_TENANCY_STORAGE_DURABLE_ROOT', storage_path('app/corex/tenancy-durable')),
        'ephemeral_root' => env('COREX_TENANCY_STORAGE_EPHEMERAL_ROOT', storage_path('app/corex/tenancy-ephemeral')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Host identification (P2.10, B-11 §5.4 п.1–3)
    |--------------------------------------------------------------------------
    | `enabled=false` (boxed profile, AC-28) registers neither the tenant
    | middleware group nor the aliases — the package pushes nothing onto the
    | host application's global middleware stack either way (D10). The cache
    | holds host → account id only (never a miss) in `cache_store`, which is
    | a host-owned central store; the Tenant host must keep it outside tenant facilities.
    */
    'host_identification' => [
        'enabled' => (bool) env('COREX_TENANCY_HOST_IDENTIFICATION_ENABLED', false),
        'cache' => true,
        'cache_ttl' => 300,
        'cache_store' => null, // null = default store
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom-domain trust SPI (P3.1)
    |--------------------------------------------------------------------------
    |
    | CoreX owns only the durable trusted-state transition. A consuming
    | application must bind all four contracts when it deliberately enables
    | this lane; no default DNS, HTTP, TLS, credential, or proof adapter ships
    | with the package.
    |
    */
    'domain_trust' => [
        'enabled' => (bool) env('COREX_TENANCY_DOMAIN_TRUST_ENABLED', false),
        'challenge_ttl_seconds' => (int) env('COREX_TENANCY_DOMAIN_TRUST_CHALLENGE_TTL_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account status gate (P2.10, B-11 §5.1)
    |--------------------------------------------------------------------------
    | `payment_route`/`export_route` accept either a route NAME or a path
    | pattern (matched via Request::is()); null = no such route configured,
    | the owner-exception never applies. The package owns no payment/export
    | page of its own — hardcoding a route name here would be a product
    | assumption this pure primitives package must not make.
    */
    'status_gate' => [
        'payment_route' => null,
        'export_route' => null,
        'read_only_methods' => ['GET', 'HEAD', 'OPTIONS'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloud OIDC login lane (P2.22, D123/D127/D129/D130) — default OFF
    |--------------------------------------------------------------------------
    | `enabled=false` (boxed profile, AC-28) registers NEITHER the
    | routes/oidc.php routes NOR the EnforceSessionLifetime middleware — the
    | package pushes nothing onto the host application's stack either way
    | (D10), and firebase/php-jwt code never executes. `issuer` is the ONLY
    | endpoint named here; authorize/token/JWKS endpoints always come from
    | the Discovery document (`Oidc\OidcDiscovery`), never hardcoded (D123).
    */
    'oidc' => [
        'enabled' => (bool) env('COREX_TENANCY_OIDC_ENABLED', false),

        'issuer' => env('COREX_TENANCY_OIDC_ISSUER'),
        'client_id' => env('COREX_TENANCY_OIDC_CLIENT_ID'),
        'client_secret' => env('COREX_TENANCY_OIDC_CLIENT_SECRET'),
        'redirect_uri' => env('COREX_TENANCY_OIDC_REDIRECT_URI'),

        // RS256-only allow-list (D123) — checked against the id_token
        // HEADER before JWT::decode() ever runs (Oidc\IdTokenVerifier).
        'allowed_algs' => ['RS256'],
        'leeway_seconds' => (int) env('COREX_TENANCY_OIDC_LEEWAY_SECONDS', 30),

        // The guard `Auth::loginUsingId()`/`Auth::logout()` operate on —
        // the consuming app's own guard name, this package assumes nothing
        // beyond what it is told.
        'guard' => env('COREX_TENANCY_OIDC_GUARD', 'web'),

        'discovery' => [
            'cache_store' => env('COREX_TENANCY_OIDC_DISCOVERY_CACHE_STORE'), // null = default store
            'cache_ttl' => (int) env('COREX_TENANCY_OIDC_DISCOVERY_CACHE_TTL', 3600),
        ],

        'jwks' => [
            'cache_store' => env('COREX_TENANCY_OIDC_JWKS_CACHE_STORE'), // null = default store
            'cache_ttl' => (int) env('COREX_TENANCY_OIDC_JWKS_CACHE_TTL', 3600),
            // D130: ONE forced refetch per unknown kid, gated by this cooldown.
            'refetch_cooldown_seconds' => (int) env('COREX_TENANCY_OIDC_JWKS_REFETCH_COOLDOWN_SECONDS', 60),
        ],

        // P2.11 — grant validation (`root_impersonation_grants`, D143) +
        // read-only invariant (D141). `enabled=false` (default): an `imp`
        // claim is rejected (403) and neither the write-guard middleware
        // nor the Eloquent listeners are registered (AC-28).
        //
        'impersonation' => [
            'enabled' => (bool) env('COREX_TENANCY_OIDC_IMPERSONATION_ENABLED', false),
        ],

        // AC-15 — enforced by Http\Middleware\EnforceSessionLifetime
        // regardless of the consuming app's own `session.lifetime`.
        'max_session_lifetime_minutes' => (int) env('COREX_TENANCY_OIDC_MAX_SESSION_LIFETIME_MINUTES', 720),
    ],

    // Profile rules are enforced in the atomic grant claim. Production dev
    // requires an explicit dangerous-mode acknowledgement.
    'impersonation' => [
        'profile' => env('COREX_TENANCY_IMPERSONATION_PROFILE', 'standard'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace resolution (P2.23, B-11 §5.4 п.4, AC-18)
    |--------------------------------------------------------------------------
    | Wire a route with `->middleware(['tenant', 'tenancy.workspace'])` — in
    | that order, so the workspace only narrows AFTER 'tenant' has proven the
    | session belongs to this account. A route WITHOUT the `tenancy.workspace`
    | alias keeps the account-wide admin context (B-11 §5.4 п.4) — the
    | package registers no product routes of its own; `/w/{slug}` and its
    | route-binding are the consuming application's concern (D133).
    */
    'workspace' => [
        'path_segment' => env('COREX_TENANCY_WORKSPACE_PATH_SEGMENT', 'w'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account lifecycle FSM (P2.12, B-11 §5.1)
    |--------------------------------------------------------------------------
    | `trial_days` — window `AccountLifecycle::startTrial()` grants before
    | `trial_ends_at`. `grace_days` — window `AccountLifecycle::startGrace()`
    | grants before `grace_until`; `purge_after` is always `grace_until` plus
    | a further fixed 30 days (§7.3 п.7 retention), not itself configurable.
    */
    'trial_days' => (int) env('COREX_TENANCY_TRIAL_DAYS', 14),
    'grace_days' => (int) env('COREX_TENANCY_GRACE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Platform domain (P2.12, AC-13)
    |--------------------------------------------------------------------------
    | Own config key rather than a hardcoded product suffix (D91 — corex/*
    | stays product-agnostic): `AccountLifecycle::startTrial()` provisions
    | the platform domain as `<slug>.<platform_domain>`. The default is only
    | a neutral placeholder — every real deployment overrides it.
    */
    'platform_domain' => env('COREX_TENANCY_PLATFORM_DOMAIN', 'example.test'),

    'wakeup' => [
        // The portable default never touches the root database. A deployment
        // explicitly chooses root_registry only after installing axiomasoft/corex-scheduler.
        'driver' => env('COREX_TENANCY_WAKEUP_DRIVER', 'local_sweep'),
        'root_connection' => env('COREX_TENANCY_WAKEUP_ROOT_CONNECTION', 'pgsql'),
        'lease_seconds' => (int) env('COREX_TENANCY_WAKEUP_LEASE_SECONDS', 60),
    ],
];
