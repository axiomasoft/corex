<?php

declare(strict_types=1);

return [
    'lease_seconds' => 60,
    'max_attempts' => 5,
    // kind => ['handler' => DueWakeupHandler::class, 'version' => 'stable/1', 'accept_legacy_unstamped' => false]
    'handlers' => [],
];
