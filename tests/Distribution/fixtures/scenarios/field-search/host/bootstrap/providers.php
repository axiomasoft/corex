<?php

declare(strict_types=1);

use App\Providers\FieldSearchServiceProvider;
use App\Providers\ScenarioServiceProvider;

return [
    'App\\Providers\\AppServiceProvider',
    ScenarioServiceProvider::class,
    FieldSearchServiceProvider::class,
];
