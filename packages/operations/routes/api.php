<?php

declare(strict_types=1);

use CoreX\Operations\Errors\Internal\OperationErrorBoundary;
use CoreX\Operations\Internal\Http\ApiController;
use CoreX\Operations\Internal\Http\DiscoveryController;
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
