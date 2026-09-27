<?php

declare(strict_types=1);

use CoreX\Api\ApiController;
use CoreX\Api\DiscoveryController;
use CoreX\Api\OperationErrorBoundary;
use Illuminate\Support\Facades\Route;

Route::get('/', [DiscoveryController::class, 'root'])->name('corex.api.discovery');
Route::get('{version}/capabilities', [DiscoveryController::class, 'capabilities'])
    ->where('version', 'v[1-9][0-9]*')
    ->name('corex.api.capabilities');
Route::get('{version}/openapi.json', [DiscoveryController::class, 'openapi'])
    ->where('version', 'v[1-9][0-9]*')
    ->name('corex.api.openapi');
Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '{version}/{path?}', ApiController::class)
    ->where('version', 'v[1-9][0-9]*')
    ->where('path', '.*')
    ->middleware(OperationErrorBoundary::class)
    ->name('corex.api.operation');
