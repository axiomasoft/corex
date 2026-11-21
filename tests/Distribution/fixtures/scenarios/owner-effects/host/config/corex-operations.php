<?php

declare(strict_types=1);

return [
    'routes' => ['enabled' => false],
    'cost' => ['enabled' => true, 'installation_id' => 'owner-effects', 'scope_hmac_secret' => 'fixture-only-owner-effects-budget-scope',
        'capacity' => 1000, 'restore_rate' => 1, 'enforced_levels' => ['global'], 'reservation_ttl_seconds' => 3600, 'terminal_retention_seconds' => 2592000],
    'schema' => [
        'tenant' => ['connection' => 'scenario_a', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_A')],
        'budget' => ['connection' => 'owner_budget', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_BUDGET')],
    ],
];
