<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Test-only fixture (D105) — the real `wsp_workspaces` table belongs to
// axiomasoft/corex-tenancy (DB_SCHEMA.md §3.4), which axiomasoft/corex-access does not depend on
// (boxed doctrine, AC-28). Only the FK target (uuid PK) is reproduced here,
// plus `settings` jsonb (default `{}`) and `path` ltree (P2.9, D117): the
// cascade_view predicate reads both from a real Postgres row.
return new class extends Migration
{
    public function up(): void
    {
        // Trusted extension since PG13 (mirrors axiomasoft/corex's sys_departments
        // migration) — needed for the `path <@` cascade query below.
        DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');

        Schema::create('wsp_workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->jsonb('settings')->default('{}');
        });

        DB::statement('ALTER TABLE wsp_workspaces ADD COLUMN path ltree NOT NULL');
        DB::statement('CREATE INDEX wsp_workspaces_path_gist_fixture ON wsp_workspaces USING GIST (path)');
    }

    public function down(): void
    {
        Schema::dropIfExists('wsp_workspaces');
    }
};
