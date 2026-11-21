<?php

declare(strict_types=1);

return [
    'require_physical_tenancy' => false,
    'cost' => [
        'enabled' => true,
        'installation_id' => 'installed-bulk-transfer',
        'scope_hmac_secret' => str_repeat('b', 64),
        'capacity' => 1000,
        'restore_rate' => 1,
        'enforced_levels' => ['global'],
        'reservation_ttl_seconds' => 3600,
        'terminal_retention_seconds' => 2592000,
    ],
    'bulk' => [
        'enabled' => true,
        'worker' => 'api-bulk',
        'lease_seconds' => 30,
        'retry_seconds' => 5,
        'row_limit' => 8,
        'time_limit_seconds' => 20,
        'max_recovery_attempts' => 5,
        'result_retention_seconds' => 3600,
        'metadata_retention_seconds' => 86400,
        'idempotency_retention_seconds' => 3600,
    ],
    'schema' => [
        'tenant' => ['connection' => 'pgsql', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_A')],
        'budget' => ['connection' => 'scenario_budget', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_BUDGET')],
    ],
];
