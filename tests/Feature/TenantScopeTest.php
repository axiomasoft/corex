<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Tenancy\ContainerTenantService;
use CoreX\Tests\Stubs\Tenant;
use CoreX\Tests\Stubs\Widget;

beforeEach(function (): void {
    // Activate multi-tenancy for this suite by binding a real tenant service.
    app()->singleton(TenantService::class, ContainerTenantService::class);
});

it('auto-stamps the tenant and isolates rows by active tenant', function (): void {
    $tenants = app(TenantService::class);

    $tenants->setCurrent(new Tenant('tenant-a'));
    $a = Widget::create(['name' => 'A']);
    expect($a->tenant_id)->toBe('tenant-a');

    $tenants->setCurrent(new Tenant('tenant-b'));
    Widget::create(['name' => 'B']);

    expect(Widget::count())->toBe(1)
        ->and(Widget::sole()->name)->toBe('B');

    $tenants->setCurrent(new Tenant('tenant-a'));
    expect(Widget::count())->toBe(1)
        ->and(Widget::sole()->name)->toBe('A');
});

it('can bypass the tenant scope', function (): void {
    $tenants = app(TenantService::class);

    $tenants->setCurrent(new Tenant('tenant-a'));
    Widget::create(['name' => 'A']);

    $tenants->setCurrent(new Tenant('tenant-b'));
    Widget::create(['name' => 'B']);

    expect(Widget::withoutTenant()->count())->toBe(2)
        ->and(Widget::forTenant('tenant-a')->count())->toBe(1);
});

it('does not scope when no tenant is active', function (): void {
    app(TenantService::class)->forget();

    Widget::create(['name' => 'global']);

    expect(Widget::count())->toBe(1);
});
