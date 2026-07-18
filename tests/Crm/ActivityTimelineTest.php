<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Tests\Stubs\Tenant;
use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateContact;
use Flex\Crm\Actions\LogActivity;
use Flex\Crm\Enums\ActivityType;
use Flex\Crm\Events\ActivityLogged;
use Flex\Crm\Models\Activity;
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
        ->and($activity->subject_type)->toBe('crm.contact') // morph handle from EntityRegistry, not FQCN
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
