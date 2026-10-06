<?php

declare(strict_types=1);

use CoreX\Database\Contracts\SchemaSets;
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
use Illuminate\Support\Str;

uses()->group('pg');

it('stores and resolves UUIDv7 activity subjects on PostgreSQL', function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
    $this->migrateSchemaSets(['flex/crm:tenant'], DB::getDefaultConnection());
    $ctx = TestContext::seed('pgsql')->current();
    $subject = Company::query()->create(['name' => 'Subject', 'workspace_id' => $ctx->tenant->workspace->id]);
    $activity = app(LogActivity::class)->handle($subject, ActivityType::Call, 'UUIDv7 ID');
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
    $lead = app(CreateLead::class)->handle(title: 'Inquiry', contactName: 'Jane');
    $contact = app(ConvertLead::class)->handle($lead, $pipeline);
    expect((string) $contact->deals()->sole()->getKey())->toBe((string) $lead->fresh()->converted_deal_id)
        ->and(DB::table('crm_deal_contacts')->count())->toBe(1);
});

it('widens a legacy activity morph column before a UUID insert', function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    (require app(SchemaSets::class)->get('flex/crm:tenant')->path.'/2026_06_14_000160_create_crm_activities_table.php')->up();
    DB::statement('ALTER TABLE crm_activities ALTER COLUMN subject_id TYPE varchar(26) USING subject_id::text');
    $migration = require app(SchemaSets::class)->get('flex/crm:tenant')->path.'/2026_09_14_000001_widen_activity_subject_id.php';
    $migration->up();
    $id = (string) Str::uuid7();
    DB::table('crm_activities')->insert(['id' => $id, 'subject_id' => $id, 'subject_type' => 'crm.company', 'type' => 'note', 'summary' => 'Legacy UUID']);
    expect(DB::table('crm_activities')->value('subject_id'))->toBe($id);
});
