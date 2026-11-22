<?php

declare(strict_types=1);

return [
    'require_physical_tenancy' => getenv('COREX_SCENARIO_VARIANT') === 'cloud',
    'routes' => ['enabled' => true, 'prefix' => 'api', 'middleware' => []],
    'bulk' => ['enabled' => getenv('COREX_SCENARIO_VARIANT') === 'equipment'],
    'schema' => [
        'tenant' => ['connection' => getenv('COREX_SCENARIO_VARIANT') === 'cloud' ? 'scenario_a' : 'pgsql', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_A')],
        'budget' => ['connection' => 'scenario_budget', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_BUDGET')],
    ],
];
