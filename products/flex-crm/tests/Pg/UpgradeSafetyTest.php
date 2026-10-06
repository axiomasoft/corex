<?php

declare(strict_types=1);

use CoreX\Database\Contracts\SchemaSets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    foreach (['2026_06_14_000130_create_crm_companies_table.php', '2026_06_14_000140_create_crm_contacts_table.php'] as $file) {
        (require app(SchemaSets::class)->get('flex/crm:tenant')->path.'/'.$file)->up();
    }
});

it('refuses automatic legacy backfill before altering any CRM data', function (): void {
    $id = (string) Str::uuid7();
    DB::table('crm_companies')->insert(['id' => $id, 'name' => 'Legacy', 'website' => 'https://legacy.test', 'custom_fields' => '{"external":true}']);
    $migration = require app(SchemaSets::class)->get('flex/crm:tenant')->path.'/2026_09_14_000002_company_contact_parity.php';
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'CRM_PARITY_UPGRADE_REQUIRES_EXPORT');
    expect(DB::table('crm_companies')->where('id', $id)->value('website'))->toBe('https://legacy.test')
        ->and(Schema::hasColumn('crm_companies', 'custom_fields'))->toBeTrue()->and(Schema::hasTable('crm_sources'))->toBeFalse();
});

it('checks prerequisite ownership before any destructive schema operation', function (): void {
    $migration = require app(SchemaSets::class)->get('flex/crm:tenant')->path.'/2026_09_14_000002_company_contact_parity.php';
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'prerequisite [users]');
    expect(Schema::hasColumn('crm_companies', 'website'))->toBeTrue()->and(Schema::hasColumn('crm_contacts', 'company_id'))->toBeTrue();
});
