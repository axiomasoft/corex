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
        Schema::create('tsk_stages', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'tsk_stages_pk');
            $table->uuid('project_id');
            $table->string('code', 64);
            $table->jsonb('name');
            $table->string('color', 16)->nullable();
            $table->bigInteger('position')->default(0);
            $table->string('auto_status', 16)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign(['project_id'], 'tsk_stages_project_id_fk')->references(['id'])->on('tsk_projects')->onDelete('cascade')->onUpdate('no action');
            $table->unique(['project_id', 'code'], 'tsk_stages_project_id_code_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE tsk_stages RENAME CONSTRAINT tsk_stages_pkey TO tsk_stages_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tsk_stages ADD CONSTRAINT tsk_stages_auto_status_ck CHECK (auto_status IN (\'in_progress\',\'done\'))');

            DB::statement('COMMENT ON COLUMN tsk_stages.auto_status IS \'Перенос карточки в колонку опционально файрит FSM задачи (task.start / task.complete). NULL = чисто визуальная колонка\'');
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

        Schema::dropIfExists('tsk_stages');
    }
};
