<?php

declare(strict_types=1);

use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;

beforeAll(static function (): void {
    TestCase::$hostIdentificationEnabled = true;
});

afterAll(static function (): void {
    TestCase::$hostIdentificationEnabled = false;
});

it('разрешает physical account до аутентификации пользователя', function (): void {
    app(Kernel::class);

    $priority = app('router')->middlewarePriority;
    $tenantIndex = array_search(ResolveTenantFromHost::class, $priority, true);
    $authIndex = array_search(AuthenticatesRequests::class, $priority, true);

    expect($tenantIndex)
        ->toBeInt()
        ->toBeLessThan($authIndex);
});
