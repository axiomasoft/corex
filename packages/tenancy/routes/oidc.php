<?php

declare(strict_types=1);

use CoreX\Tenancy\Authentication\Internal\Http\Controllers\LogoutController;
use CoreX\Tenancy\Authentication\Internal\Http\Controllers\OidcLoginController;
use Illuminate\Support\Facades\Route;

// The provider loads these routes when corex-tenancy.oidc.enabled is true.
// The OIDC handlers require an active tenant context.
Route::middleware('tenant')->group(function (): void {
    Route::get('/auth/login', [OidcLoginController::class, 'redirect'])
        ->name('corex.tenancy.oidc.login');

    Route::get('/auth/callback', [OidcLoginController::class, 'callback'])
        ->name('corex.tenancy.oidc.callback');

    Route::post('/auth/logout', [LogoutController::class, 'logout'])
        ->name('corex.tenancy.oidc.logout');
});
