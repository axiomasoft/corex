<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Executed from the package schema set on its declared connection.
return new class extends Migration
{
    public function up(): void
    {
        $table = 'sys_audit_log';

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id')->primary();
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

        // Monotonic audit cursor (D42): clock-free bigserial assigned in insert
        // order. Keyset pagination `WHERE seq > :cursor ORDER BY seq` is the ONLY
        // stable ordering of this journal — hence the btree index on seq.
        DB::statement("ALTER TABLE {$table} ADD COLUMN seq bigserial");

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_audit_actor_ck CHECK (actor_type IN ('user','agent','api_key','system'))");
        DB::statement("CREATE INDEX sys_audit_seq_ix ON {$table} (seq)");
        DB::statement("CREATE INDEX sys_audit_entity_ix ON {$table} (entity_type, entity_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_actor_ix ON {$table} (actor_type, actor_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_wsp_ix ON {$table} (workspace_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_brin_ix ON {$table} USING BRIN (occurred_at)");
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_audit_log');
    }
};
