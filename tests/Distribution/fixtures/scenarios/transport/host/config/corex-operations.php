<?php

declare(strict_types=1);

use App\TransportProof\TrustedRequest;

return [
    'require_physical_tenancy' => getenv('COREX_SCENARIO_VARIANT') === 'cloud',
    'routes' => ['enabled' => true, 'prefix' => 'api', 'middleware' => [TrustedRequest::class]],
    'bulk' => ['enabled' => true, 'row_limit' => 8, 'time_limit_seconds' => 20],
    'cost' => [
        'enabled' => true,
        'installation_id' => 'installed-transport',
        'scope_hmac_secret' => str_repeat('t', 64),
        'capacity' => 1000,
        'restore_rate' => 1,
        'enforced_levels' => ['global'],
    ],
    'schema' => [
        'tenant' => ['connection' => getenv('COREX_SCENARIO_VARIANT') === 'cloud' ? 'scenario_a' : 'pgsql', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_A')],
        'budget' => ['connection' => 'scenario_budget', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_BUDGET')],
    ],
];
