<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-only (D12): jsonb + CHECK + UNIQUE NULLS NOT DISTINCT have no sqlite
// equivalent — lives in migrations/pg/, NOT loaded by
// CoreServiceProvider::loadMigrationsFrom (default sqlite lane), mirroring
// flex/modules' mod_* migrations. PG-lane tests migrate it explicitly
// (tests/Feature/SettingsTest.php), same pattern as ModuleLifecycleTest.
// Replaces corex_settings (D1 — greenfield, no prod data to migrate).
// See DB_SCHEMA.md §3.2 sys_settings for the column-by-column source.
return new class extends Migration
{
    public function up(): void
    {
        $table = Config::settingsTable();

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('namespace', 64);
            $table->string('key', 190);
            $table->string('scope_type', 16)->default('account');
            $table->uuid('scope_id')->nullable();
            $table->jsonb('value');
            $table->boolean('is_sensitive')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_scope_ck CHECK (scope_type IN ('account','workspace','user'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_scope_id_ck CHECK ((scope_type = 'account' AND scope_id IS NULL) OR (scope_type <> 'account' AND scope_id IS NOT NULL))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_uq UNIQUE NULLS NOT DISTINCT (namespace, key, scope_type, scope_id)");
        DB::statement("CREATE INDEX sys_settings_scope_ix ON {$table} (scope_type, scope_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::settingsTable());
    }
};
