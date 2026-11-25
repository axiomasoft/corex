<?php

declare(strict_types=1);

use Brick\Money\Money;
use Flex\Crm\Deal\Actions\CreateDeal;
use Flex\Crm\Deal\Actions\MoveDealToStage;
use Flex\Crm\Deal\Enums\DealStatus;
use Flex\Crm\Deal\Events\DealCreated;
use Flex\Crm\Deal\Events\DealMovedToStage;
use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Deal\Models\Pipeline;
use Flex\Crm\Deal\Models\Stage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->pipeline = Pipeline::create(['entity_type' => 'crm.deal', 'code' => 'sales', 'name' => ['en' => 'Sales'], 'is_default' => true]);
    $this->newStage = Stage::create(['pipeline_id' => $this->pipeline->id, 'code' => 'new', 'name' => ['en' => 'New'], 'semantic' => 'open', 'position' => 0]);
    $this->wonStage = Stage::create(['pipeline_id' => $this->pipeline->id, 'code' => 'won', 'name' => ['en' => 'Won'], 'position' => 1, 'semantic' => 'won']);
});

it('creates a deal at the first stage with money', function (): void {
    Event::fake([DealCreated::class]);

    $deal = resolve(CreateDeal::class)->handle($this->pipeline, 'Acme', Money::of(1500, 'USD'));

    expect($deal->stage_id)->toBe($this->newStage->id)
        ->and($deal->status)->toBe(DealStatus::Open)
        ->and(Str::isUuid($deal->id))->toBeTrue()
        ->and($deal->amount->getAmount()->__toString())->toBe('1500.00')
        ->and($deal->amount->getCurrency()->getCurrencyCode())->toBe('USD');

    // Money persisted as minor units, not a float.
    expect($deal->fresh()->amount->getMinorAmount()->toInt())->toBe(150000);

    Event::assertDispatched(DealCreated::class);
});

it('moves a deal to a won stage and marks it won', function (): void {
    Event::fake([DealMovedToStage::class]);

    $deal = resolve(CreateDeal::class)->handle($this->pipeline, 'Acme');
    $moved = resolve(MoveDealToStage::class)->handle($deal, $this->wonStage);

    expect($moved->stage_id)->toBe($this->wonStage->id)
        ->and($moved->status)->toBe(DealStatus::Won);

    Event::assertDispatched(DealMovedToStage::class);
});

it('refuses to create a deal in a pipeline without stages', function (): void {
    $empty = Pipeline::create(['entity_type' => 'crm.deal', 'code' => 'empty', 'name' => ['en' => 'Empty']]);

    resolve(CreateDeal::class)->handle($empty, 'orphan');
})->throws(RuntimeException::class);

it('rejects a stage from another pipeline without changing the deal or emitting an event', function (): void {
    $deal = resolve(CreateDeal::class)->handle($this->pipeline, 'Unchanged');
    $other = Pipeline::create(['entity_type' => 'crm.deal', 'code' => 'other', 'name' => ['en' => 'Other']]);
    $stage = Stage::create(['pipeline_id' => $other->id, 'code' => 'other', 'name' => ['en' => 'Other stage'], 'position' => 0]);
    Event::fake([DealMovedToStage::class]);
    expect(fn () => resolve(MoveDealToStage::class)->handle($deal, $stage))->toThrow(ValidationException::class);
    expect($deal->fresh()->stage_id)->toBe($this->newStage->id);
    Event::assertNotDispatched(DealMovedToStage::class);
});

it('clears optional amount without clearing mandatory currency', function (): void {
    $deal = resolve(CreateDeal::class)->handle($this->pipeline, 'Optional amount', Money::ofMinor(125, 'USD'));
    $deal->amount = null;
    $deal->save();
    expect($deal->fresh()->amount)->toBeNull()
        ->and($deal->fresh()->getRawOriginal('currency'))->toBe('USD');
});

it('rolls back a vetoed stage update without a success event', function (): void {
    $deal = resolve(CreateDeal::class)->handle($this->pipeline, 'Veto');
    Event::fake([DealMovedToStage::class]);
    Deal::updating(fn (): bool => false);
    expect(fn () => resolve(MoveDealToStage::class)->handle($deal, $this->wonStage))->toThrow(ValidationException::class);
    expect($deal->fresh()->stage_id)->toBe($this->newStage->id);
    Event::assertNotDispatched(DealMovedToStage::class);
});

it('refuses a pipeline that belongs to another entity type', function (): void {
    $leads = Pipeline::create(['entity_type' => 'crm.lead', 'code' => 'intake', 'name' => ['en' => 'Intake']]);
    Stage::create(['pipeline_id' => $leads->id, 'code' => 'new', 'name' => ['en' => 'New'], 'position' => 0]);

    expect(fn () => resolve(CreateDeal::class)->handle($leads, 'Misfiled'))->toThrow(AuthorizationException::class);
    expect(Deal::query()->count())->toBe(0);
});

it('enforces the required fields of the first stage on create and of the target stage on move', function (): void {
    $gated = Pipeline::create(['entity_type' => 'crm.deal', 'code' => 'gated', 'name' => ['en' => 'Gated']]);
    Stage::create(['pipeline_id' => $gated->id, 'code' => 'qualify', 'name' => ['en' => 'Qualify'], 'position' => 0, 'required_fields' => ['owner_id']]);

    expect(fn () => resolve(CreateDeal::class)->handle($gated, 'Unowned'))->toThrow(ValidationException::class);

    $deal = resolve(CreateDeal::class)->handle($this->pipeline, 'Unowned');
    $proposal = Stage::create(['pipeline_id' => $this->pipeline->id, 'code' => 'proposal', 'name' => ['en' => 'Proposal'], 'position' => 2, 'required_fields' => ['owner_id']]);

    expect(fn () => resolve(MoveDealToStage::class)->handle($deal, $proposal))->toThrow(ValidationException::class);
    expect($deal->fresh()->stage_id)->toBe($this->newStage->id);
});
