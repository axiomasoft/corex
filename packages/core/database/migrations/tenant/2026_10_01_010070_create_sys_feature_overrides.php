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
        $table = 'sys_feature_overrides';

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_feature_overrides_pk');
            $table->uuid('flag_id');
            $table->foreign(['flag_id'], 'sys_feature_overrides_flag_fk')->references('id')->on('sys_feature_flags')->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->uuid('scope_id');
            $table->boolean('is_enabled');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        Schema::getConnection()->statement('ALTER TABLE sys_feature_overrides RENAME CONSTRAINT sys_feature_overrides_pkey TO sys_feature_overrides_pk');

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_feature_overrides_scope_ck CHECK (scope_type IN ('workspace','user','department'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_feature_overrides_flag_scope_uq UNIQUE (flag_id, scope_type, scope_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_feature_overrides');
    }
};
