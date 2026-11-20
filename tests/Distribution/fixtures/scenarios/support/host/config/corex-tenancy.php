<?php

declare(strict_types=1);

return [
    'wakeup' => [
        'driver' => getenv('COREX_SCENARIO_CASE') === 'worker-scope' && getenv('COREX_SCENARIO_VARIANT') === 'cloud'
            ? 'root_registry'
            : 'local_sweep',
        'root_connection' => 'pgsql',
        'lease_seconds' => 60,
    ],
];
