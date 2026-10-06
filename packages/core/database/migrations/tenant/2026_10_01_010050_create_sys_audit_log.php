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
        $table = 'sys_audit_log';

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_audit_log_pk');
            $table->timestampTz('occurred_at', precision: 6)->useCurrent();
            $table->string('actor_type', 16);
            $table->uuid('actor_id')->nullable();
            $table->uuid('workspace_id')->nullable();
            $table->string('entity_type', 64)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->string('action', 64);
            $table->jsonb('changes')->nullable();
            $table->jsonb('context')->default('{}');
            $table->timestampTz('created_at', precision: 6)->useCurrent();
        });

        Schema::getConnection()->statement('ALTER TABLE sys_audit_log RENAME CONSTRAINT sys_audit_log_pkey TO sys_audit_log_pk');

        // A sequence keeps keyset pagination independent of wall-clock ordering.
        DB::statement("ALTER TABLE {$table} ADD COLUMN seq bigserial");

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_audit_log_actor_ck CHECK (actor_type IN ('user','agent','api_key','system','support'))");
        DB::statement("CREATE INDEX sys_audit_log_seq_ix ON {$table} (seq)");
        DB::statement("CREATE INDEX sys_audit_log_entity_ix ON {$table} (entity_type, entity_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_log_actor_ix ON {$table} (actor_type, actor_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_log_workspace_ix ON {$table} (workspace_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_log_occurred_brin_ix ON {$table} USING BRIN (occurred_at)");
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_audit_log');
    }
};
