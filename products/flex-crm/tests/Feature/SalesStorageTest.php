<?php

declare(strict_types=1);

use Flex\Crm\Activity\Actions\LogActivity;
use Flex\Crm\Activity\Enums\ActivityType;
use Flex\Crm\Lead\Actions\ConvertLead;
use Flex\Crm\Lead\Actions\CreateLead;
use Flex\Crm\Lead\Enums\LeadStatus;
use Flex\Crm\Lead\Events\LeadConverted;
use Flex\Crm\Lead\Events\LeadCreated;
use Flex\Crm\Lead\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

it('persists canonical lead fields with trusted provenance', function (): void {
    $lead = app(CreateLead::class)->handle(title: 'Inbound inquiry', contactName: 'Jane Doe');
    expect($lead->fresh()->title)->toBe('Inbound inquiry')
        ->and($lead->status)->toBe(LeadStatus::Open)
        ->and($lead->created_by)->toBe($this->crmContext->current()->actor->toArray())
        ->and($lead->workspace_id)->toBe($this->crmContext->current()->tenant->workspace->id);
});

it('refuses conversion without explicit human identity and preserves the lead', function (): void {
    $lead = app(CreateLead::class)->handle(title: 'A deal title is not a human name');
    expect(fn () => app(ConvertLead::class)->handle($lead))->toThrow(ValidationException::class);
    expect($lead->fresh()->status)->toBe(LeadStatus::Open)->and(DB::table('crm_contacts')->count())->toBe(0);
});

it('stores activity kinds and canonical target associations', function (): void {
    $lead = app(CreateLead::class)->handle(title: 'Inquiry', contactName: 'Jane');
    $activity = app(LogActivity::class)->handle(subject: $lead, type: ActivityType::Call, summary: 'Intro call');
    expect($activity->fresh()->kind)->toBe(ActivityType::Call)
        ->and($activity->subject)->toBe('Intro call')
        ->and($lead->activities()->sole()->id)->toBe($activity->id)
        ->and(DB::table('crm_activity_targets')->sole()->entity_type)->toBe('crm.lead');
});

it('does not represent legacy statuses or note and task as activities', function (): void {
    expect(LeadStatus::tryFrom('new'))->toBeNull()
        ->and(LeadStatus::tryFrom('qualified'))->toBeNull()
        ->and(ActivityType::tryFrom('note'))->toBeNull()
        ->and(ActivityType::tryFrom('task'))->toBeNull();
});

it('rejects creation veto without persisted rows or success events', function (): void {
    Event::fake([LeadCreated::class]);
    Lead::saving(fn (): bool => false);
    expect(fn () => app(CreateLead::class)->handle(title: 'Vetoed'))->toThrow(ValidationException::class);
    expect(DB::table('crm_leads')->count())->toBe(0);
    Event::assertNotDispatched(LeadCreated::class);
});

it('rolls back the entire conversion when the final lead update is vetoed', function (): void {
    $lead = app(CreateLead::class)->handle(title: 'Inquiry', contactName: 'Jane');
    Event::fake([LeadConverted::class]);
    Lead::updating(fn (): bool => false);
    expect(fn () => app(ConvertLead::class)->handle($lead))->toThrow(ValidationException::class);
    expect($lead->fresh()->status)->toBe(LeadStatus::Open)
        ->and(DB::table('crm_contacts')->count())->toBe(0);
    Event::assertNotDispatched(LeadConverted::class);
});
