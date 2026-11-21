<?php

declare(strict_types=1);
use App\Providers\BulkTransferServiceProvider;
use App\Providers\ScenarioServiceProvider;

return [
    'App\\Providers\\AppServiceProvider',
    ScenarioServiceProvider::class,
    BulkTransferServiceProvider::class,
];
