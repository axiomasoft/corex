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
        Schema::create('tsk_projects', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'tsk_projects_pk');
            $table->uuid('workspace_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
            $table->uuid('owner_id')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->jsonb('settings')->default('{}');
            $table->jsonb('created_by');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->softDeletesTz();

            $table->foreign(['owner_id'], 'tsk_projects_owner_id_fk')->references(['id'])->on('users')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['workspace_id'], 'tsk_projects_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE tsk_projects RENAME CONSTRAINT tsk_projects_pkey TO tsk_projects_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {

            DB::statement('ALTER TABLE tsk_projects ADD CONSTRAINT tsk_projects_status_ck CHECK (status IN (\'active\',\'archived\'))');

            DB::statement('CREATE INDEX tsk_projects_ws_ix ON tsk_projects (workspace_id, status) WHERE deleted_at IS NULL');
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

        Schema::dropIfExists('tsk_projects');
    }
};
