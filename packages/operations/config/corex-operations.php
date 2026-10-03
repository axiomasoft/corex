<?php

declare(strict_types=1);

return [
    // Boxed API deployments must explicitly opt out of physical tenancy.
    'require_physical_tenancy' => true,

    'routes' => [
        'enabled' => true,
        'prefix' => 'api',
        'middleware' => [],
    ],

    'versions' => [
        ['major' => 'v1', 'status' => 'stable', 'supported_through' => null],
    ],

    'default_major' => 'v1',
    'product' => 'corex',
    'build' => 'dev',

    'idempotency' => [
        'retention_seconds' => 86400,
        'lease_seconds' => 30,
    ],

    'cost' => [
        // Enabling requires an explicit shared schema and a host-owned HMAC secret.
        'enabled' => false,
        'installation_id' => 'corex',
        'scope_hmac_secret' => '',
        'capacity' => 10000,
        'restore_rate' => 1000,
        'enforced_levels' => ['global'],
        'reservation_ttl_seconds' => 3600,
        'terminal_retention_seconds' => 2592000,
    ],

    'bulk' => [
        'enabled' => true,
        'worker' => 'api-bulk',
        'lease_seconds' => 30,
        'retry_seconds' => 5,
        'result_retention_seconds' => 86400,
        'metadata_retention_seconds' => 2592000,
        'idempotency_retention_seconds' => 86400,
    ],

    'cursor' => [
        'ttl_seconds' => 3600,
        'current_key_id' => 'primary',
        'keyring' => [
            'primary' => '',
        ],
    ],

    'discovery' => 'public',
    'capabilities' => [],
];
