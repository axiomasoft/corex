<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Crm\Actions\ConvertLead;
use CoreX\Crm\Actions\CreateLead;
use CoreX\Crm\Enums\LeadSource;
use CoreX\Crm\Enums\LeadStatus;
use CoreX\Crm\Events\LeadConverted;
use CoreX\Crm\Models\Deal;
use CoreX\Crm\Models\Pipeline;
use CoreX\Crm\Models\Stage;
use CoreX\Tests\Stubs\Tenant;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));
    $this->pipeline = Pipeline::create(['name' => 'Sales']);
    Stage::create(['pipeline_id' => $this->pipeline->id, 'name' => 'New', 'position' => 0]);
});

it('converts a lead into contact, company and deal', function (): void {
    Event::fake([LeadConverted::class]);

    $lead = app(CreateLead::class)->handle('Jane Doe', LeadSource::Website, email: 'jane@acme.test', companyName: 'Acme', ownerId: 'u1');
    $contact = app(ConvertLead::class)->handle($lead, $this->pipeline, withCompany: true);
    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Converted)
        ->and($lead->converted_contact_id)->toBe($contact->id)
        ->and($lead->converted_company_id)->not->toBeNull()
        ->and($lead->converted_deal_id)->not->toBeNull()
        ->and($contact->company_id)->toBe($lead->converted_company_id);

    $deal = Deal::query()->findOrFail($lead->converted_deal_id);
    expect($deal->contact_id)->toBe($contact->id)
        ->and($deal->company_id)->toBe($lead->converted_company_id);

    Event::assertDispatched(LeadConverted::class);
});

it('converts a lead without company or deal', function (): void {
    $lead = app(CreateLead::class)->handle('No Co', LeadSource::Referral);
    $contact = app(ConvertLead::class)->handle($lead);
    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Converted)
        ->and($lead->converted_company_id)->toBeNull()
        ->and($lead->converted_deal_id)->toBeNull()
        ->and($contact->company_id)->toBeNull();
});
