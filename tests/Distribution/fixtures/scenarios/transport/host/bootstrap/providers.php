<?php

declare(strict_types=1);

use App\Providers\ScenarioServiceProvider;
use App\Providers\TransportProofServiceProvider;

return [
    'App\\Providers\\AppServiceProvider',
    ScenarioServiceProvider::class,
    TransportProofServiceProvider::class,
];
