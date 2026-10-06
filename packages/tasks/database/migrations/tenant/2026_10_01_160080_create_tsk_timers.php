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
        Schema::create('tsk_timers', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->primary(['user_id'], 'tsk_timers_pk');
            $table->uuid('task_id');
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign(['task_id'], 'tsk_timers_task_id_fk')->references(['id'])->on('tsk_tasks')->onDelete('cascade')->onUpdate('no action');
            $table->foreign(['user_id'], 'tsk_timers_user_id_fk')->references(['id'])->on('users')->onDelete('cascade')->onUpdate('no action');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE tsk_timers RENAME CONSTRAINT tsk_timers_pkey TO tsk_timers_pk');
        }

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

        Schema::dropIfExists('tsk_timers');
    }
};
