<?php

declare(strict_types=1);

use Flex\Crm\Tests\Support\AccountFixture;
use Flex\Crm\Tests\Support\StorageCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
});

it('matches every actual enabled column constraint and index against approved DDL', function (): void {
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true])->assertSuccessful();
    DB::transaction(function (): void {
        DB::statement('CREATE SCHEMA crm_reference_test');
        DB::statement('SET LOCAL search_path TO crm_reference_test,public');
        DB::unprepared(file_get_contents(__DIR__.'/../Fixtures/approved-storage.sql'));
        $expected = StorageCatalogue::read(DB::connection(), 'crm_reference_test');
        $actual = StorageCatalogue::read(DB::connection(), 'public');
        expect(array_keys($actual))->toBe(array_keys($expected));
        foreach ($expected as $table => $catalogue) {
            expect($actual[$table])->toBe($catalogue, $table);
        }
        DB::statement('DROP SCHEMA crm_reference_test CASCADE');
    });
});

it('refuses populated legacy sales tables before changing any storage', function (string $table): void {
    foreach (glob(__DIR__.'/../../database/migrations/*.php') as $path) {
        if (str_contains($path, '2026_09_15')) {
            continue;
        }
        (require $path)->up();
    }
    $id = (string) Str::uuid7();
    $values = match ($table) {
        'crm_leads' => ['id' => $id, 'name' => 'Legacy', 'source' => 'website', 'custom_fields' => '{"provenance":"keep"}'],
        'crm_pipelines' => ['id' => $id, 'name' => 'Legacy'],
        'crm_activities' => ['id' => $id, 'type' => 'note', 'summary' => 'Original', 'subject_type' => 'crm.lead', 'subject_id' => $id, 'metadata' => '{"keep":true}'],
    };
    DB::table($table)->insert($values);
    $rowBefore = (array) DB::table($table)->where('id', $id)->first();
    $before = StorageCatalogue::read(DB::connection(), 'public');
    $migration = require __DIR__.'/../../database/migrations/2026_09_15_000001_full_storage_parity.php';
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'CRM_PARITY_UPGRADE_REQUIRES_EXPORT');
    expect(StorageCatalogue::read(DB::connection(), 'public'))->toBe($before)
        ->and((array) DB::table($table)->where('id', $id)->first())->toBe($rowBefore)
        ->and(Schema::hasTable('crm_lost_reasons'))->toBeFalse();
})->with(['crm_leads', 'crm_pipelines', 'crm_activities']);
