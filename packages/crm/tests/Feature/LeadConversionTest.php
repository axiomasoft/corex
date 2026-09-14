<?php

declare(strict_types=1);

use Flex\Crm\Actions\ConvertLead;
use Flex\Crm\Actions\CreateLead;
use Flex\Crm\Enums\LeadStatus;
use Flex\Crm\Events\LeadConverted;
use Flex\Crm\Models\Company;
use Flex\Crm\Models\Contact;
use Flex\Crm\Models\Deal;
use Flex\Crm\Models\Pipeline;
use Flex\Crm\Models\Stage;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->pipeline = Pipeline::create(['entity_type' => 'crm.deal', 'code' => 'sales', 'name' => ['en' => 'Sales']]);
    Stage::create(['pipeline_id' => $this->pipeline->id, 'code' => 'new', 'name' => ['en' => 'New'], 'position' => 0]);
});

it('converts a lead into contact, company and deal', function (): void {
    Event::fake([LeadConverted::class]);

    $lead = app(CreateLead::class)->handle(title: 'Inquiry', contactName: 'Jane Doe', email: 'jane@acme.test', companyName: 'Acme', ownerId: $this->crmContext->context->principalId);
    $contact = app(ConvertLead::class)->handle($lead, $this->pipeline, withCompany: true);
    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Converted)
        ->and($lead->converted_contact_id)->toBe($contact->id)
        ->and($lead->converted_company_id)->not->toBeNull()
        ->and($lead->converted_deal_id)->not->toBeNull()
        ->and($contact->companies()->sole()->id)->toBe($lead->converted_company_id);

    $deal = Deal::query()->findOrFail($lead->converted_deal_id);
    expect($deal->contacts()->sole()->id)->toBe($contact->id)
        ->and($deal->company_id)->toBe($lead->converted_company_id);
    expect($deal->contacts()->sole()->id)->toBe($contact->id)
        ->and($deal->company()->sole()->id)->toBe($lead->converted_company_id)
        ->and($deal->pipeline()->sole()->id)->toBe($this->pipeline->id)
        ->and($deal->stage()->sole()->pipeline_id)->toBe($this->pipeline->id)
        ->and($contact->deals()->sole()->id)->toBe($deal->id)
        ->and($deal->company->deals()->sole()->id)->toBe($deal->id)
        ->and($this->pipeline->deals()->count())->toBe(1);

    Event::assertDispatched(LeadConverted::class);
});

it('converts a lead without company or deal', function (): void {
    $lead = app(CreateLead::class)->handle(title: 'No Co', contactName: 'Jane');
    $contact = app(ConvertLead::class)->handle($lead);
    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Converted)
        ->and($lead->converted_company_id)->toBeNull()
        ->and($lead->converted_deal_id)->toBeNull()
        ->and($contact->companies()->count())->toBe(0);
});

it('returns the same conversion on retry even when passed a stale lead instance', function (): void {
    Event::fake([LeadConverted::class]);
    $lead = app(CreateLead::class)->handle(title: 'Retry', contactName: 'Jane', companyName: 'Once');
    $stale = $lead->fresh();
    $first = app(ConvertLead::class)->handle($lead, $this->pipeline, withCompany: true);
    $second = app(ConvertLead::class)->handle($stale, $this->pipeline, withCompany: true);
    expect($second->id)->toBe($first->id)
        ->and(Contact::query()->count())->toBe(1)
        ->and(Company::query()->count())->toBe(1)
        ->and(Deal::query()->count())->toBe(1);
    Event::assertDispatchedTimes(LeadConverted::class, 1);
});
