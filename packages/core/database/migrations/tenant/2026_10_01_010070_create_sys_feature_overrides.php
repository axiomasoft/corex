<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $table = 'sys_feature_overrides';

        $this->create($table, function (Blueprint $table): void {
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

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_feature_overrides_scope_ck CHECK (scope_type IN ('workspace','user','department'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_feature_overrides_flag_scope_uq UNIQUE (flag_id, scope_type, scope_id)");
    }

    public function down(): void
    {
        $this->drop('sys_feature_overrides');
    }
};
