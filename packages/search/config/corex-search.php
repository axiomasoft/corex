<?php

declare(strict_types=1);

return [
    'driver' => 'null',
    'cluster' => 'primary',
    'retention_seconds' => 604800,
    'cursor_secret' => null,
    'postgres' => [
        'text_search_config' => 'simple',
        'scan_budget' => 1000,
    ],
];
