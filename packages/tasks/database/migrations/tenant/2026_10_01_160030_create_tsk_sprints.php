<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('tsk_sprints', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'tsk_sprints_pk');
            $table->uuid('project_id');
            $table->string('name', 190);
            $table->text('goal')->nullable();
            $table->string('status', 16)->default('planned');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign(['project_id'], 'tsk_sprints_project_id_fk')->references(['id'])->on('tsk_projects')->onDelete('cascade')->onUpdate('no action');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tsk_sprints ADD CONSTRAINT tsk_sprints_dates_ck CHECK (ends_on >= starts_on)');

            DB::statement('ALTER TABLE tsk_sprints ADD CONSTRAINT tsk_sprints_status_ck CHECK (status IN (\'planned\',\'active\',\'completed\'))');
            DB::statement('CREATE UNIQUE INDEX tsk_sprints_active_uq ON tsk_sprints (project_id) WHERE status = \'active\'');
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

        $this->drop('tsk_sprints');
    }
};
