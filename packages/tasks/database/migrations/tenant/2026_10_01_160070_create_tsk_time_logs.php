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
        Schema::create('tsk_time_logs', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'tsk_time_logs_pk');
            $table->uuid('task_id');
            $table->uuid('user_id');
            $table->bigInteger('minutes');
            $table->date('spent_on');
            $table->text('comment')->nullable();
            $table->string('source', 8)->default('manual');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign(['task_id'], 'tsk_time_logs_task_id_fk')->references(['id'])->on('tsk_tasks')->onDelete('cascade')->onUpdate('no action');
            $table->foreign(['user_id'], 'tsk_time_logs_user_id_fk')->references(['id'])->on('users')->onDelete('restrict')->onUpdate('no action');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE tsk_time_logs RENAME CONSTRAINT tsk_time_logs_pkey TO tsk_time_logs_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tsk_time_logs ADD CONSTRAINT tsk_time_logs_minutes_ck CHECK (minutes > 0)');
            DB::statement('ALTER TABLE tsk_time_logs ADD CONSTRAINT tsk_time_logs_source_ck CHECK (source IN (\'manual\',\'timer\'))');

        }

        DB::statement('CREATE INDEX "tsk_time_logs_user_ix" on "tsk_time_logs" ("user_id", "spent_on")');
        DB::statement('CREATE INDEX "tsk_time_logs_task_ix" on "tsk_time_logs" ("task_id")');
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

        Schema::dropIfExists('tsk_time_logs');
    }
};
