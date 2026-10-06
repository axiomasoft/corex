<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Database\Laravel\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Executed from the package schema set on its declared connection.
return new class extends Migration
{
    public function up(): void
    {
        $table = app(CoreOptions::class)->settingsTable;

        Schema::create($table, function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('namespace', 64);
            $table->string('key', 190);
            $table->string('scope_type', 16)->default('account');
            IdColumns::reference($table, 'scope_id')->nullable();
            $table->jsonb('value');
            $table->boolean('is_sensitive')->default(false);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_scope_ck CHECK (scope_type IN ('account','workspace','user'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_scope_id_ck CHECK ((scope_type = 'account' AND scope_id IS NULL) OR (scope_type <> 'account' AND scope_id IS NOT NULL))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_uq UNIQUE NULLS NOT DISTINCT (namespace, key, scope_type, scope_id)");
        // Beyond the DRAFT DB_SCHEMA catalog (A91, documented deviation): a
        // scope lookup index for the cascade resolver. Kept, not dropped — the
        // catalog is silent on it, not in conflict; recorded in P1.19 report.
        DB::statement("CREATE INDEX sys_settings_scope_ix ON {$table} (scope_type, scope_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists(app(CoreOptions::class)->settingsTable);
    }
};
