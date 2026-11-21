<?php

declare(strict_types=1);
use App\Providers\LifecycleServiceProvider;
use App\Providers\ScenarioServiceProvider;

return [
    'App\\Providers\\AppServiceProvider',
    ScenarioServiceProvider::class,
    LifecycleServiceProvider::class,
];
