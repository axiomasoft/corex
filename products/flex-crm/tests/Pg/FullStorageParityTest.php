<?php

declare(strict_types=1);

use Flex\Crm\Tests\Support\AccountFixture;
use Flex\Crm\Tests\Support\StorageCatalogue;
use Illuminate\Support\Facades\DB;

uses()->group('pg');

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
});

it('matches every actual enabled column constraint and index against approved DDL', function (): void {
    $this->migrateSchemaSets(['flex/crm:tenant'], DB::getDefaultConnection());
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
