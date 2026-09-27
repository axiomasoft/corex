<?php

declare(strict_types=1);

return [
    'digest' => [
        'enabled' => false,
        'accept_legacy_unstamped' => false,
    ],
    'delivery' => [
        'claim_seconds' => 60,
        'max_attempts' => 5,
    ],
];
