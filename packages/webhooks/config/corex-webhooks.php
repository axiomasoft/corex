<?php

declare(strict_types=1);

return [
    // Remote I/O needs both flags plus an explicitly bound, host-owned key ring.
    'delivery_enabled' => false,
    'worker_ready' => false,
    'lease_seconds' => 30,
    'body_retention_seconds' => 604800,
    'history_retention_seconds' => 2592000,
    'replay_horizon_seconds' => 604800,
    'organization_denied_cidrs' => [],
    'topics' => [],
    'projectors' => [],
];
