<?php

declare(strict_types=1);

return [
    'require_physical_tenancy' => false,
    'routes' => ['enabled' => false],
    'schema' => [
        'tenant' => ['connection' => 'pgsql', 'driver' => 'pgsql', 'database' => env('DB_DATABASE')],
        'budget' => ['connection' => 'scenario_b', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_B')],
    ],
];
