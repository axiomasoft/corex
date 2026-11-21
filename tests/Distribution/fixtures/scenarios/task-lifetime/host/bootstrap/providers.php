<?php

declare(strict_types=1);

use App\Providers\ScenarioServiceProvider;
use App\TaskLifetime\TaskLifetimeCatalogRegistration;
use App\TaskLifetime\TaskLifetimeServiceProvider;

return [
    'App\\Providers\\AppServiceProvider',
    ScenarioServiceProvider::class,
    TaskLifetimeCatalogRegistration::class,
    TaskLifetimeServiceProvider::class,
];
