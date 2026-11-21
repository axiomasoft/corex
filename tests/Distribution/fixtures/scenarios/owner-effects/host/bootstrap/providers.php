<?php

declare(strict_types=1);
use App\Providers\OwnerEffectsServiceProvider;
use App\Providers\ScenarioServiceProvider;

return [
    'App\\Providers\\AppServiceProvider',
    ScenarioServiceProvider::class,
    OwnerEffectsServiceProvider::class,
];
