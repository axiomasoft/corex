<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'sys_settings';

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_settings_pk');
            $table->string('namespace', 64);
            $table->string('key', 190);
            $table->string('scope_type', 16)->default('account');
            $table->uuid('scope_id')->nullable();
            $table->jsonb('value');
            $table->boolean('is_sensitive')->default(false);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        Schema::getConnection()->statement('ALTER TABLE sys_settings RENAME CONSTRAINT sys_settings_pkey TO sys_settings_pk');

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_scope_ck CHECK (scope_type IN ('account','workspace','user'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_scope_id_ck CHECK ((scope_type = 'account' AND scope_id IS NULL) OR (scope_type <> 'account' AND scope_id IS NOT NULL))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_uq UNIQUE NULLS NOT DISTINCT (namespace, key, scope_type, scope_id)");
        DB::statement("CREATE INDEX sys_settings_scope_ix ON {$table} (scope_type, scope_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_settings');
    }
};
