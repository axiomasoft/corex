<?php

declare(strict_types=1);

use Brick\Money\Money;
use CoreX\Contracts\TenantService;
use CoreX\Crm\Actions\CreateDeal;
use CoreX\Crm\Actions\MoveDealToStage;
use CoreX\Crm\Enums\DealStatus;
use CoreX\Crm\Events\DealCreated;
use CoreX\Crm\Events\DealMovedToStage;
use CoreX\Crm\Models\Pipeline;
use CoreX\Crm\Models\Stage;
use CoreX\Tests\Stubs\Tenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));

    $this->pipeline = Pipeline::create(['name' => 'Sales', 'is_default' => true]);
    $this->newStage = Stage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'position' => 0]);
    $this->wonStage = Stage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'Won', 'position' => 1, 'is_won' => true]);
});

it('creates a deal at the first stage, tenant-stamped, with money', function (): void {
    Event::fake([DealCreated::class]);

    $deal = app(CreateDeal::class)->handle($this->pipeline, 'Acme', Money::of(1500, 'USD'));

    expect($deal->stage_id)->toBe($this->newStage->id)
        ->and($deal->status)->toBe(DealStatus::Open)
        ->and($deal->tenant_id)->toBe('tenant-a')
        ->and(Str::isUuid($deal->id))->toBeTrue()
        ->and($deal->amount->getAmount()->__toString())->toBe('1500.00')
        ->and($deal->amount->getCurrency()->getCurrencyCode())->toBe('USD');

    // Money persisted as minor units, not a float.
    expect($deal->fresh()->amount->getMinorAmount()->toInt())->toBe(150000);

    Event::assertDispatched(DealCreated::class);
});

it('moves a deal to a won stage and marks it won', function (): void {
    Event::fake([DealMovedToStage::class]);

    $deal = app(CreateDeal::class)->handle($this->pipeline, 'Acme');
    $moved = app(MoveDealToStage::class)->handle($deal, $this->wonStage);

    expect($moved->stage_id)->toBe($this->wonStage->id)
        ->and($moved->status)->toBe(DealStatus::Won);

    Event::assertDispatched(DealMovedToStage::class);
});

it('refuses to create a deal in a pipeline without stages', function (): void {
    $empty = Pipeline::create(['name' => 'Empty']);

    app(CreateDeal::class)->handle($empty, 'orphan');
})->throws(RuntimeException::class);
