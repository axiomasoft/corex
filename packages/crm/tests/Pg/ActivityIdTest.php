<?php

declare(strict_types=1);

use Flex\Crm\Actions\LogActivity;
use Flex\Crm\Enums\ActivityType;
use Flex\Crm\Models\Company;
use Illuminate\Support\Facades\DB;

uses()->group('pg');

it('stores and resolves activity subjects on PostgreSQL for the configured ID strategy', function (string $strategy): void {
    config(['database.default' => 'pgsql', 'corex.ids.strategy' => $strategy]);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true])->assertSuccessful();
    $subject = Company::query()->create(['name' => 'Subject']);
    $activity = app(LogActivity::class)->handle($subject, ActivityType::Note, 'Compatible ID');
    expect((string) $activity->fresh()->subject->getKey())->toBe((string) $subject->getKey());
})->with(['uuid7', 'uuid', 'ulid', 'int']);

it('widens a legacy activity morph column before a UUID insert', function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true])->assertSuccessful();
    DB::statement('ALTER TABLE crm_activities ALTER COLUMN subject_id TYPE varchar(26) USING subject_id::text');
    $migration = require __DIR__.'/../../database/migrations/2026_09_14_000001_widen_activity_subject_id.php';
    $migration->up();
    $subject = Company::query()->create(['name' => 'UUID subject']);
    $activity = app(LogActivity::class)->handle($subject, ActivityType::Note, 'Upgraded ID');
    expect($activity->fresh()->subject->id)->toBe($subject->id);
});
