<?php

declare(strict_types=1);

use CoreX\Enums\IdStrategy;
use Flex\Crm\Actions\ConvertLead;
use Flex\Crm\Actions\CreateLead;
use Flex\Crm\Actions\LogActivity;
use Flex\Crm\Enums\ActivityType;
use Flex\Crm\Models\Company;
use Flex\Crm\Models\Pipeline;
use Flex\Crm\Models\Stage;
use Flex\Crm\Tests\Support\AccountFixture;
use Flex\Crm\Tests\Support\TestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

it('stores and resolves activity subjects on PostgreSQL for the configured ID strategy', function (string $strategy): void {
    config(['database.default' => 'pgsql', 'corex.ids.strategy' => $strategy]);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true])->assertSuccessful();
    $ctx = TestContext::seed('pgsql')->current();
    $subject = Company::query()->create(['name' => 'Subject', 'workspace_id' => $strategy === 'int' ? (int) $ctx->tenant->workspace->id : $ctx->tenant->workspace->id]);
    $activity = app(LogActivity::class)->handle($subject, ActivityType::Call, 'Compatible ID');
    expect((string) $activity->fresh()->targets()->sole()->entity->getKey())->toBe((string) $subject->getKey());
})->with(['uuid7', 'uuid', 'ulid', 'int']);

it('converts with canonical deal associations under enum configured IDs', function (string $strategy): void {
    config(['database.default' => 'pgsql', 'corex.ids.strategy' => $strategy]);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true])->assertSuccessful();
    TestContext::seed('pgsql');
    config(['corex.ids.strategy' => IdStrategy::from($strategy)]);
    $pipeline = Pipeline::create(['entity_type' => 'crm.deal', 'code' => 'sales', 'name' => ['en' => 'Sales']]);
    Stage::create(['pipeline_id' => $pipeline->id, 'code' => 'open', 'name' => ['en' => 'Open']]);
    $lead = app(CreateLead::class)->handle(title: 'Inquiry', contactName: 'Jane');
    $contact = app(ConvertLead::class)->handle($lead, $pipeline);
    expect((string) $contact->deals()->sole()->getKey())->toBe((string) $lead->fresh()->converted_deal_id)
        ->and(DB::table('crm_deal_contacts')->count())->toBe(1);
})->with(['ulid', 'int']);

it('widens a legacy activity morph column before a UUID insert', function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    (require __DIR__.'/../../database/migrations/2026_06_14_000160_create_crm_activities_table.php')->up();
    DB::statement('ALTER TABLE crm_activities ALTER COLUMN subject_id TYPE varchar(26) USING subject_id::text');
    $migration = require __DIR__.'/../../database/migrations/2026_09_14_000001_widen_activity_subject_id.php';
    $migration->up();
    $id = (string) Str::uuid7();
    DB::table('crm_activities')->insert(['id' => $id, 'subject_id' => $id, 'subject_type' => 'crm.company', 'type' => 'note', 'summary' => 'Legacy UUID']);
    expect(DB::table('crm_activities')->value('subject_id'))->toBe($id);
});
