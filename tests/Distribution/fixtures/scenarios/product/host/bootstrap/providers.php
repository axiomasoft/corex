<?php

declare(strict_types=1);

use App\Providers\ProductProofServiceProvider;
use App\Providers\ScenarioServiceProvider;

return [
    'App\\Providers\\AppServiceProvider',
    ScenarioServiceProvider::class,
    ProductProofServiceProvider::class,
];
