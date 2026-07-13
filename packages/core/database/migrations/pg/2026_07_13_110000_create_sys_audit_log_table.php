<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-only (D12): jsonb + CHECK + BRIN have no sqlite equivalent — lives in
// migrations/pg/, NOT loaded by CoreServiceProvider::loadMigrationsFrom
// (default sqlite lane), mirroring sys_settings (P1.7) and flex/modules'
// mod_* migrations. PG-lane tests migrate it explicitly (AuditLogTest),
// same pattern as SettingsTest/ModuleLifecycleTest.
// Replaces corex_audit_logs (D1 — greenfield, no prod data to migrate).
// No updated_at: DB_SCHEMA.md §Конфликт-2 (resolved 2026-07-13) — append-only
// with zero legitimate post-insert fields does not get one.
// See DB_SCHEMA.md §3.2 sys_audit_log for the column-by-column source.
return new class extends Migration
{
    public function up(): void
    {
        $table = Config::auditLogsTable();

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->string('actor_type', 16);
            $table->uuid('actor_id')->nullable();
            $table->uuid('workspace_id')->nullable();
            $table->string('entity_type', 64)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->string('action', 64);
            $table->jsonb('changes')->nullable();
            $table->jsonb('context')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_audit_actor_ck CHECK (actor_type IN ('user','agent','api_key','system'))");
        DB::statement("CREATE INDEX sys_audit_entity_ix ON {$table} (entity_type, entity_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_actor_ix ON {$table} (actor_type, actor_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_wsp_ix ON {$table} (workspace_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_brin_ix ON {$table} USING BRIN (occurred_at)");
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::auditLogsTable());
    }
};
