<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-first (D12/D9): CHECK IN(...) instead of a PG enum, id uuid PK without
// a DB default (the app generates UUIDv7 — ADR-003/D3), jsonb, timestamptz.
// See DB_SCHEMA.md §3.3 mod_modules for the column-by-column source.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 190);
            $table->string('state', 16);
            $table->string('version', 32);
            $table->string('schema_version', 64)->nullable();
            $table->string('edition', 16)->default('standard');
            $table->timestampTz('enabled_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->string('disable_reason', 16)->nullable();
            $table->jsonb('config')->default('{}');
            $table->jsonb('meta')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique('name', 'mod_modules_name_uq');
            $table->index('state', 'mod_modules_state_ix');
        });

        DB::statement("ALTER TABLE mod_modules ADD CONSTRAINT mod_modules_state_ck CHECK (state IN ('enabled','disabled','archived','purged'))");
        DB::statement("ALTER TABLE mod_modules ADD CONSTRAINT mod_modules_edition_ck CHECK (edition IN ('standard','pro'))");
        DB::statement("ALTER TABLE mod_modules ADD CONSTRAINT mod_modules_reason_ck CHECK (disable_reason IN ('manual','billing','dependency') OR disable_reason IS NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_modules');
    }
};
