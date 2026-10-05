<?php

declare(strict_types=1);

use CoreX\Tenancy\Authentication\Internal\Http\Controllers\LogoutController;
use CoreX\Tenancy\Authentication\Internal\Http\Controllers\OidcLoginController;
use Illuminate\Support\Facades\Route;

// AC-14/AC-15 — loaded only when `corex-tenancy.oidc.enabled` (TenancyServiceProvider::boot()).
// 'tenant' — tenancy is already resolved by the time these run (P2.10).
Route::middleware('tenant')->group(function (): void {
    Route::get('/auth/login', [OidcLoginController::class, 'redirect'])
        ->name('corex.tenancy.oidc.login');

    Route::get('/auth/callback', [OidcLoginController::class, 'callback'])
        ->name('corex.tenancy.oidc.callback');

    Route::post('/auth/logout', [LogoutController::class, 'logout'])
        ->name('corex.tenancy.oidc.logout');
});
