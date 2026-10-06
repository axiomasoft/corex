<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->migrateSchemaSets(['axiomasoft/corex-panels:tenant'], 'pgsql');
});

it('creates the saved-view scope lock before the composite view foreign key', function (): void {
    expect(Schema::hasTable('sys_saved_view_scopes'))->toBeTrue()
        ->and(Schema::hasTable('sys_saved_views'))->toBeTrue();

    $foreignKeys = DB::select("select constraint_name from information_schema.table_constraints where table_name = 'sys_saved_views' and constraint_type = 'FOREIGN KEY'");

    expect($foreignKeys)->not->toBeEmpty();
})->group('pg');
