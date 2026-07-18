<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Tests\Stubs\Tenant;
use Flex\Crm\Actions\CreateDeal;
use Flex\Crm\Models\Deal;
use Flex\Crm\Models\Pipeline;
use Flex\Crm\Models\Stage;

it('isolates deals by tenant via the core TenantScope', function (): void {
    $tenants = app(TenantService::class);

    $tenants->setCurrent(new Tenant('tenant-a'));
    $pipeline = Pipeline::create(['name' => 'A']);
    Stage::create(['pipeline_id' => $pipeline->id, 'name' => 'New']);
    app(CreateDeal::class)->handle($pipeline, 'a-deal');

    $tenants->setCurrent(new Tenant('tenant-b'));
    expect(Deal::query()->count())->toBe(0);

    $tenants->setCurrent(new Tenant('tenant-a'));
    expect(Deal::query()->count())->toBe(1);
});
