<?php

declare(strict_types=1);

use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateContact;
use Flex\Crm\Actions\LogActivity;
use Flex\Crm\Data\CompanyData;
use Flex\Crm\Data\ContactData;
use Flex\Crm\Enums\ActivityType;
use Flex\Crm\Events\ActivityLogged;
use Flex\Crm\Models\Activity;
use Illuminate\Support\Facades\Event;

it('logs a polymorphic activity resolved through the morph alias', function (): void {
    Event::fake([ActivityLogged::class]);

    $contact = app(CreateContact::class)->handle(new ContactData(nameFirst: 'Jane'));
    $activity = app(LogActivity::class)->handle($contact, ActivityType::Call, 'Intro call', body: 'Went well');

    expect($activity->kind)->toBe(ActivityType::Call)
        ->and($activity->targets()->sole()->entity_type)->toBe('crm.contact')
        ->and($activity->targets()->sole()->entity->is($contact))->toBeTrue()
        ->and($contact->activities()->count())->toBe(1);

    Event::assertDispatched(ActivityLogged::class);
});

it('attaches activities to different subject types', function (): void {
    $contact = app(CreateContact::class)->handle(new ContactData(nameFirst: 'A'));
    $company = app(CreateCompany::class)->handle(new CompanyData(name: 'Acme'));

    app(LogActivity::class)->handle($contact, ActivityType::Email, 'email');
    app(LogActivity::class)->handle($company, ActivityType::Meeting, 'meet');

    expect(Activity::query()->count())->toBe(2)
        ->and($contact->activities()->count())->toBe(1)
        ->and($company->activities()->count())->toBe(1);
});
