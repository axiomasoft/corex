<?php

declare(strict_types=1);

use Flex\Crm\Activity\Actions\LogActivity;
use Flex\Crm\Activity\Enums\ActivityType;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Deal\Models\Pipeline;
use Flex\Crm\Deal\Models\Stage;
use Flex\Crm\Lead\Actions\ConvertLead;
use Flex\Crm\Lead\Actions\CreateLead;
use Flex\Crm\Tests\Support\AccountFixture;
use Flex\Crm\Tests\Support\TestContext;
use Illuminate\Support\Facades\DB;

uses()->group('pg');

it('stores and resolves UUIDv7 activity subjects on PostgreSQL', function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
    $this->migrateSchemaSets(['flex/crm:tenant'], DB::getDefaultConnection());
    $ctx = TestContext::seed('pgsql')->current();
    $subject = Company::query()->create(['name' => 'Subject', 'workspace_id' => $ctx->tenant->workspace->id]);
    $activity = resolve(LogActivity::class)->handle($subject, ActivityType::Call, 'UUIDv7 ID');
    expect((string) $activity->fresh()->targets()->sole()->entity->getKey())->toBe((string) $subject->getKey())
        ->and($subject->getKey()[14])->toBe('7')
        ->and($activity->getKey()[14])->toBe('7');
});

it('converts with canonical UUIDv7 deal associations', function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
    $this->migrateSchemaSets(['flex/crm:tenant'], DB::getDefaultConnection());
    TestContext::seed('pgsql');
    $pipeline = Pipeline::create(['entity_type' => 'crm.deal', 'code' => 'sales', 'name' => ['en' => 'Sales']]);
    Stage::create(['pipeline_id' => $pipeline->id, 'code' => 'open', 'name' => ['en' => 'Open']]);
    $lead = resolve(CreateLead::class)->handle(title: 'Inquiry', contactName: 'Jane');
    $contact = resolve(ConvertLead::class)->handle($lead, $pipeline);
    expect((string) $contact->deals()->sole()->getKey())->toBe((string) $lead->fresh()->converted_deal_id)
        ->and(DB::table('crm_deal_contacts')->count())->toBe(1);
});
