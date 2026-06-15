<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Crm\Actions\CreateCompany;
use CoreX\Crm\Actions\CreateContact;
use CoreX\Crm\Actions\LogActivity;
use CoreX\Crm\Enums\ActivityType;
use CoreX\Crm\Events\ActivityLogged;
use CoreX\Crm\Models\Activity;
use CoreX\Tests\Stubs\Tenant;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));
});

it('logs a polymorphic activity resolved through the morph alias', function (): void {
    Event::fake([ActivityLogged::class]);

    $contact = app(CreateContact::class)->handle(firstName: 'Jane');
    $activity = app(LogActivity::class)->handle($contact, ActivityType::Call, 'Intro call', body: 'Went well');

    expect($activity->tenant_id)->toBe('tenant-a')
        ->and($activity->type)->toBe(ActivityType::Call)
        ->and($activity->subject_type)->toBe('crm_contact') // morph alias from EntityRegistry, not FQCN
        ->and($activity->subject->is($contact))->toBeTrue()
        ->and($contact->activities()->count())->toBe(1);

    Event::assertDispatched(ActivityLogged::class);
});

it('attaches activities to different subject types', function (): void {
    $contact = app(CreateContact::class)->handle(firstName: 'A');
    $company = app(CreateCompany::class)->handle('Acme');

    app(LogActivity::class)->handle($contact, ActivityType::Note, 'note');
    app(LogActivity::class)->handle($company, ActivityType::Meeting, 'meet');

    expect(Activity::query()->count())->toBe(2)
        ->and($contact->activities()->count())->toBe(1)
        ->and($company->activities()->count())->toBe(1);
});
