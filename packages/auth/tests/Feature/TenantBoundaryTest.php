<?php

declare(strict_types=1);

use CoreX\Auth\Tenancy\TenantBoundary;
use CoreX\Auth\Tests\Stubs\Project;
use CoreX\Auth\Tests\Stubs\Tenant;
use CoreX\Contracts\TenantService;

beforeEach(function (): void {
    $this->tenants = app(TenantService::class);
    $this->boundary = app(TenantBoundary::class);
});

it('permits resources that are not tenant-owned', function (): void {
    expect($this->boundary->permits(new stdClass))->toBeTrue();
});

it('permits any resource when no tenant is active', function (): void {
    $this->tenants->forget();
    $project = Project::create(['name' => 'p']);

    expect($this->boundary->permits($project))->toBeTrue();
});

it('denies a resource owned by another tenant', function (): void {
    $this->tenants->setCurrent(new Tenant('tenant-a'));
    $project = Project::create(['name' => 'p']);

    $this->tenants->setCurrent(new Tenant('tenant-b'));

    expect($this->boundary->permits($project))->toBeFalse();
});

it('permits a resource owned by the active tenant', function (): void {
    $this->tenants->setCurrent(new Tenant('tenant-a'));
    $project = Project::create(['name' => 'p']);

    expect($project->tenant_id)->toBe('tenant-a')
        ->and($this->boundary->permits($project))->toBeTrue();
});
