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
        Schema::create('tsk_task_targets', function (Blueprint $table): void {
            $table->uuid('task_id');
            $table->string('entity_type', 64);
            $table->string('entity_id', 64);
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['task_id', 'entity_type', 'entity_id'], 'tsk_task_targets_pk');

            $table->foreign(['task_id'], 'tsk_task_targets_task_id_fk')->references(['id'])->on('tsk_tasks')->onDelete('cascade')->onUpdate('no action');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE tsk_task_targets RENAME CONSTRAINT tsk_task_targets_pkey TO tsk_task_targets_pk');
        }

        DB::statement('CREATE INDEX "tsk_task_targets_entity_ix" on "tsk_task_targets" ("entity_type", "entity_id")');
    }

    public function down(): void
    {
        foreach (['tsk_timers', 'tsk_time_logs', 'tsk_task_watchers', 'tsk_task_targets', 'tsk_tasks', 'tsk_sprints', 'tsk_stages', 'tsk_projects'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException(
                    "CoreX Tasks cannot roll back a non-empty canonical table [{$table}]. Remove or migrate its data explicitly first.",
                );
            }
        }

        Schema::dropIfExists('tsk_task_targets');
    }
};
