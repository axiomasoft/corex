<?php

declare(strict_types=1);

use CoreX\Operations\Internal\Http\ApiController;
use CoreX\Operations\Internal\Http\DiscoveryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DiscoveryController::class, 'root'])->name('corex.api.discovery');
Route::get('v2/capabilities', [DiscoveryController::class, 'capabilities'])->name('corex.api.capabilities');
Route::get('v2/openapi.json', [DiscoveryController::class, 'openapi'])->name('corex.api.openapi');
Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 'v2/{path?}', ApiController::class)
    ->where('path', '.*')
    ->fallback()
    ->name('corex.api.operation');
