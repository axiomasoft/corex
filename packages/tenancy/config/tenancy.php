<?php

declare(strict_types=1);

use CoreX\Tenancy\Database\TemplateClonePostgreSQLManager;
use CoreX\Tenancy\Jobs\CreateDatabaseFromTemplate;
use CoreX\Tenancy\Models\Account;
use Stancl\Tenancy\Bootstrappers;
use Stancl\Tenancy\UniqueIdentifierGenerators\UUIDv7Generator;

return [

    /*
    |--------------------------------------------------------------------------
    | Central connection (B-11 §2.1 / D14)
    |--------------------------------------------------------------------------
    | root_* migrations and models target this connection — the control-plane
    | database, never a tenant/account database. Override per-host if the
    | central DB uses a different Laravel connection name.
    */
    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | stancl/tenancy v4 pin (D8/D75/D76 — GO on 2026-07-18 PoC gate)
    |--------------------------------------------------------------------------
    | stancl/tenancy master carries no tagged release; the exact sha is the
    | supply-chain anchor (D25 fork-mirror is a deferred follow-up, OQ-4).
    */
    'stancl' => [
        'package' => 'stancl/tenancy',
        'pin' => 'dev-master#76e5f9655924271c6c9da54e2d4ccb82c6f71ea0 as 4.0.0',
        'jobpipeline_pin' => '2.0.0-rc7',
    ],

    /*
    |--------------------------------------------------------------------------
    | Account/provisioning wiring (P2.3)
    |--------------------------------------------------------------------------
    | Own top-level keys, not `tenancy.models.*`/`tenancy.database.*` — stancl's
    | own TenancyServiceProvider::register() also merges its `assets/config.php`
    | defaults into the SAME `tenancy` config key; a shallow array_merge at the
    | top level would silently drop the rest of stancl's `database`/`models`
    | subtree if we owned those keys here. TenancyServiceProvider::boot() (runs
    | after every provider's register()) projects these onto the real
    | `tenancy.models.tenant`/`tenancy.database.*` keys stancl reads.
    */
    'tenant_model' => Account::class,
    'id_generator' => UUIDv7Generator::class,
    'tenant_database_manager' => TemplateClonePostgreSQLManager::class,

    'provisioning' => [
        // TenantCreated ⇒ JobPipeline — no MigrateDatabase/SeedDatabase (R-11 §2.3 п.5).
        'pipeline' => [
            CreateDatabaseFromTemplate::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | TenancyManager bootstrappers (P2.5, R-11 §2.4 — fixed order)
    |--------------------------------------------------------------------------
    | Own key (not `bootstrappers` — that's stancl's own top-level key under
    | the SAME `tenancy` config namespace; TenancyServiceProvider::boot()
    | projects this onto it deterministically, mirroring `tenant_model`/
    | `central_connection` above). Database → Cache(prefix-mode) →
    | DatabaseSession → Queue → Filesystem → Scout-prefix — order is an
    | Implementation Rule (P2.5), not incidental.
    */
    'bootstrapper_order' => [
        Bootstrappers\DatabaseTenancyBootstrapper::class,
        Bootstrappers\CacheTenancyBootstrapper::class,
        Bootstrappers\DatabaseSessionBootstrapper::class,
        Bootstrappers\QueueTenancyBootstrapper::class,
        Bootstrappers\FilesystemTenancyBootstrapper::class,
        Bootstrappers\Integrations\ScoutPrefixBootstrapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache prefix-mode (P2.5 — NOT cache tags, v4 default)
    |--------------------------------------------------------------------------
    | `%tenant%` is replaced with the tenant key by CacheTenancyBootstrapper;
    | prepended to the store's original prefix. Own key, projected onto
    | stancl's `tenancy.cache.prefix` in boot() (same reasoning as above).
    */
    'cache_prefix' => 'tenant_%tenant%_',

];
