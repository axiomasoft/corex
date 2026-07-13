<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Append-style audit of every enable/disable/upgrade/archive/purge — used to
// diagnose partial-failure retries (AC-7/AC-13). DB_SCHEMA.md §3.3
// mod_lifecycle_log.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_lifecycle_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('module_name', 190);
            $table->string('operation', 16);
            $table->string('from_state', 16);
            $table->string('to_state', 16);
            $table->string('status', 16)->default('success');
            $table->string('actor_type', 16);
            $table->uuid('actor_id')->nullable();
            $table->jsonb('details')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement('CREATE INDEX mod_ll_module_ix ON mod_lifecycle_log (module_name, created_at DESC)');
        DB::statement("ALTER TABLE mod_lifecycle_log ADD CONSTRAINT mod_ll_op_ck CHECK (operation IN ('enable','disable','upgrade','archive','purge'))");
        DB::statement("ALTER TABLE mod_lifecycle_log ADD CONSTRAINT mod_ll_status_ck CHECK (status IN ('success','failed','started'))");
        DB::statement("ALTER TABLE mod_lifecycle_log ADD CONSTRAINT mod_ll_actor_ck CHECK (actor_type IN ('user','system','platform','cli'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_lifecycle_log');
    }
};
