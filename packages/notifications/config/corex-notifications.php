<?php

declare(strict_types=1);

return [
    'digest' => [
        'enabled' => false,
    ],
    'delivery' => [
        'claim_seconds' => 60,
        'max_attempts' => 5,
    ],
    'retention' => [
        // Positive minimum age in days of the read time and any dismissal or cancellation.
        // Retention compacts inbox content and preserves replay and provider receipts.
        'settled_days' => 90,
    ],
];
