<?php

declare(strict_types=1);

use CoreX\Audit\QueuedAuditLogger;
use CoreX\Enums\IdStrategy;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Settings\NullSettingDefaultsProvider;
use CoreX\Tenancy\NullTenantService;
use CoreX\Tenancy\SingleAccountResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Identifier strategy
    |--------------------------------------------------------------------------
    | Primary-key strategy for BaseModel. UUIDv7 is the default (ADR-003
    | revision); Ulid/Uuid/Int seams remain for boxed/legacy installs.
    */
    'ids' => [
        'strategy' => IdStrategy::Uuid7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    | Override if the defaults collide with existing tables in a host app.
    */
    'tables' => [
        'settings' => 'sys_settings',
        'audit_logs' => 'sys_audit_log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy (ADR-004)
    |--------------------------------------------------------------------------
    | TenantScope is active only when a TenantService implementation is bound
    | in the container. On-premise installs leave NullTenantService and run
    | unscoped. 'column' is the tenant foreign key on tenant-owned tables.
    */
    'tenancy' => [
        'service' => NullTenantService::class,
        'column' => 'tenant_id',
        'resolver' => null, // e.g. CoreX\Tenancy\Resolvers\SubdomainTenantResolver::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant context (B-10 §3.1 / B-11 §3.1)
    |--------------------------------------------------------------------------
    | TenantContextResolver binding. Boxed installs default to
    | SingleAccountResolver (constant context, no real tenancy). Real
    | resolvers (host/root, corex/tenancy) land in P2.
    */
    'tenant_context' => [
        'resolver' => SingleAccountResolver::class,
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
        'setting_defaults' => NullSettingDefaultsProvider::class,
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

];
