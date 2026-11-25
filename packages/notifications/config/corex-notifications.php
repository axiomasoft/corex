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
        // Days after a notification is read, dismissed or cancelled before
        // corex:notifications:prune may delete it. Unread ones are kept.
        'settled_days' => 90,
    ],
];
