<?php

declare(strict_types=1);

use CoreX\Tenancy\Http\Controllers\LogoutController;
use CoreX\Tenancy\Http\Controllers\OidcLoginController;
use CoreX\Tenancy\Http\Middleware\EnforceSessionLifetime;
use Illuminate\Support\Facades\Route;

// AC-28 — `tenancy.oidc.enabled` defaults to false (config/tenancy.php).
// This file deliberately never touches `TestCase::$oidcEnabled` (its sibling
// files OidcLoginTest.php/GlobalLogoutTest.php flip it in their own
// beforeAll()/afterAll()) — the boxed-profile assertions for BOTH
// OidcLoginTest's case р and GlobalLogoutTest's case е live here, under the
// flag's real, untouched default.
uses()->group('pg');

it('р: oidc.enabled=false → OIDC routes are not registered, the app still boots', function (): void {
    expect(config('tenancy.oidc.enabled'))->toBeFalse()
        ->and(Route::has('corex.tenancy.oidc.login'))->toBeFalse()
        ->and(Route::has('corex.tenancy.oidc.callback'))->toBeFalse()
        ->and(Route::has('corex.tenancy.oidc.logout'))->toBeFalse()
        ->and(app()->bound(OidcLoginController::class))->toBeFalse()
        ->and(app()->bound(LogoutController::class))->toBeFalse();
});

it('е: oidc.enabled=false → EnforceSessionLifetime is absent from the tenant middleware group', function (): void {
    expect(config('tenancy.oidc.enabled'))->toBeFalse()
        ->and(app('router')->getMiddlewareGroups()['tenant'] ?? [])->not->toContain(EnforceSessionLifetime::class)
        ->and(app('router')->getMiddleware())->not->toHaveKey('tenancy.oidc.session_lifetime');
});
