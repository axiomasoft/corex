<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PG-only (D12) — mirrors sys_settings/sys_audit_log: lives in migrations/pg/,
// not loaded by CoreServiceProvider::loadMigrationsFrom (sqlite default
// lane). See DB_SCHEMA.md §3.2 sys_feature_flags for the column-by-column
// source; B-10 §2 for the DDL this mirrors.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::featureFlagsTable(), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 128)->unique();
            $table->string('module', 190)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::featureFlagsTable());
    }
};
