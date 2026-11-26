<?php

declare(strict_types=1);

use CoreX\Audit\Internal\NullAuditRetention;
use CoreX\Audit\Internal\QueuedAuditLogger;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Context\Internal\NullTenancyManager;
use CoreX\Context\Internal\SingleAccountResolver;
use CoreX\Data\Internal\DenyDataScopeAuthorizer;
use CoreX\Departments\Internal\DatabaseDepartmentRepository;
use CoreX\Features\Internal\DatabaseFeatureFlags;
use CoreX\Messaging\Enums\PubSubDriver;
use CoreX\Settings\Internal\DatabaseSettingsRepository;
use CoreX\Settings\Internal\NullSettingDefaultsProvider;

return [
    'scope' => [
        'deployment_id' => env('COREX_DEPLOYMENT_ID'),
        'control_connection' => env('COREX_SCOPE_CONTROL_CONNECTION'),
        'tenant_jobs' => [],
        'central_jobs' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant context
    |--------------------------------------------------------------------------
    | TenantContextResolver binding. Boxed installs default to
    | SingleAccountResolver (constant context, no real tenancy). Real
    | resolvers (host/root, axiomasoft/corex-tenancy) are provided by the tenancy package.
    |
    | `manager`: TenancyManager binding — single switch point for
    | connection/cache/queue/fs. Boxed default is a null-manager (no-op,
    | constant context via `resolver` above); axiomasoft/corex-tenancy rebinds this to
    | DatabaseTenancyManager when the package is present (container
    | binding, never an `if(cloud)` branch).
    |
    | `impersonation`: ImpersonationService binding — boxed
    | default has no grants at all; axiomasoft/corex-tenancy rebinds this to
    | DatabaseImpersonationService under `tenancy.oidc.impersonation.enabled`.
    */
    'tenant_context' => [
        'resolver' => SingleAccountResolver::class,
        'manager' => NullTenancyManager::class,
        'impersonation' => NullImpersonationService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bindings
    |--------------------------------------------------------------------------
    | Swap any core contract implementation here.
    */
    'bindings' => [
        'settings' => DatabaseSettingsRepository::class,
        'audit_logger' => QueuedAuditLogger::class,
        'audit_retention' => NullAuditRetention::class,
        'setting_defaults' => NullSettingDefaultsProvider::class,
        'feature_flags' => DatabaseFeatureFlags::class,
        'departments' => DatabaseDepartmentRepository::class,
        'data_scope_authorizer' => DenyDataScopeAuthorizer::class,
    ],

    'storage' => [
        'durable_root' => storage_path('app/corex/durable'),
        'ephemeral_root' => storage_path('app/corex/ephemeral'),
    ],

    'views' => [
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings cache
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'cache_store' => 'array',
        'cache_ttl' => 3600,
        'cache_prefix' => 'corex.settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | PubSub (no LISTEN/NOTIFY)
    |--------------------------------------------------------------------------
    | 'null' is the boxed-safe default (no infra dependency). 'redis' is the
    | cloud default; 'database' is the boxed fallback when Redis
    | is unavailable.
    |
    | `octane_worker_opt_in`: a dedicated worker command sets this
    | to true before calling subscribe() under Octane — runningInConsole()
    | alone cannot tell an HTTP request from a worker command apart there.
    | Never set it from request-handling code.
    |
    | `retention`: corex:messaging:prune-pubsub's default --older-than window for
    | sys_pubsub_messages. Schedule it (e.g. daily, php artisan schedule)
    | longer than the worst-case subscriber downtime — pruning a message
    | before every durable cursor behind it has passed it is a silent,
    | permanent invalidation loss.
    */
    'pubsub' => [
        'driver' => PubSubDriver::Null,
        'poll_interval_microseconds' => 50_000,
        'octane_worker_opt_in' => false,
        'retention' => '30 days',
    ],

    /*
    |--------------------------------------------------------------------------
    | Messaging (MSG-001 … MSG-008)
    |--------------------------------------------------------------------------
    | Transactional outbox and idempotent consumer inbox.
    |
    | Set `lease_seconds` above the longest expected publish duration. When a
    | lease expires, another worker can claim and publish the same message.
    | `batch_size` limits one relay pass and caps the work per account.
    |
    | Retries wait min(backoff_cap_seconds, 2 ** attempt) seconds up to
    | `max_attempts`. Like the scheduler's SweepRunner, the relay uses
    | deterministic backoff with one relay per account.
    |
    | `outbox_retention` controls how long published rows are kept for
    | reconciliation. `dead_retention` keeps dead rows for investigation.
    |
    | A null `inbox_retention` keeps deduplication records indefinitely.
    | Set a finite retention period only when redelivery has a known limit.
    */
    'messaging' => [
        'lease_seconds' => 120,
        'batch_size' => 100,
        'max_attempts' => 8,
        'backoff_cap_seconds' => 300,
        'outbox_retention' => '14 days',
        'dead_retention' => '90 days',
        'inbox_retention' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Acknowledged dangerous modes
    |--------------------------------------------------------------------------
    | Exact config paths of dangerous package modes the operator accepts in
    | `production` (for example `corex-workflow.enforce_declarations`). An
    | enabled dangerous mode that is not listed here blocks process start;
    | patterns and "acknowledge everything" are not supported.
    */
    'acknowledged_dangerous_modes' => [],

    // Module runtime configuration is owned by Packman: config/modules.php.
];
