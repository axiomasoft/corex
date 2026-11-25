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
        $this->create('tsk_tasks', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'tsk_tasks_pk');
            $table->uuid('workspace_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('sprint_id')->nullable();
            $table->uuid('stage_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('priority', 8)->default('normal');
            $table->uuid('author_id')->nullable();
            $table->uuid('assignee_id')->nullable();
            $table->jsonb('created_by');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->boolean('requires_acceptance')->default(false);
            $table->uuid('recurrence_rule_id')->nullable();
            $table->timestampTz('recurrence_occurs_at')->nullable();
            $table->bigInteger('estimate_minutes')->nullable();
            $table->bigInteger('spent_minutes')->default(0);
            $table->decimal('position', total: 20, places: 10)->default(0);

            if (DB::getDriverName() === 'pgsql') {
                $table->addColumn('tsvector', 'search')->nullable();
            } else {
                $table->text('search')->nullable();
            }

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->softDeletesTz();

            $table->foreign(['assignee_id'], 'tsk_tasks_assignee_id_fk')->references(['id'])->on('users')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['author_id'], 'tsk_tasks_author_id_fk')->references(['id'])->on('users')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['parent_id'], 'tsk_tasks_parent_id_fk')->references(['id'])->on('tsk_tasks')->onDelete('cascade')->onUpdate('no action');
            $table->foreign(['project_id'], 'tsk_tasks_project_id_fk')->references(['id'])->on('tsk_projects')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['sprint_id'], 'tsk_tasks_sprint_id_fk')->references(['id'])->on('tsk_sprints')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['stage_id'], 'tsk_tasks_stage_id_fk')->references(['id'])->on('tsk_stages')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['workspace_id'], 'tsk_tasks_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
            $table->index(['parent_id'], 'tsk_tasks_parent_id_ix');
            $table->index(['stage_id'], 'tsk_tasks_stage_id_ix');
            $table->index(['workspace_id'], 'tsk_tasks_workspace_id_ix');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {

            DB::statement('ALTER TABLE tsk_tasks ADD CONSTRAINT tsk_tasks_completed_ck CHECK (status <> \'done\' OR completed_at IS NOT NULL)');

            DB::statement('ALTER TABLE tsk_tasks ADD CONSTRAINT tsk_tasks_priority_ck CHECK (priority IN (\'low\',\'normal\',\'high\',\'urgent\'))');

            DB::statement('ALTER TABLE tsk_tasks ADD CONSTRAINT tsk_tasks_recurrence_ck CHECK ((recurrence_rule_id IS NULL) = (recurrence_occurs_at IS NULL))');

            DB::statement('ALTER TABLE tsk_tasks ADD CONSTRAINT tsk_tasks_status_ck CHECK (status IN (\'open\',\'in_progress\',\'waiting\',\'review\',\'done\',\'cancelled\'))');

            DB::statement('CREATE INDEX tsk_tasks_assignee_ix ON tsk_tasks (assignee_id, status) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX tsk_tasks_due_ix ON tsk_tasks (due_at) WHERE status IN (\'open\',\'in_progress\',\'waiting\',\'review\') AND deleted_at IS NULL');
            DB::statement('CREATE INDEX tsk_tasks_kanban_ix ON tsk_tasks (project_id, stage_id, position) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX tsk_tasks_review_ix ON tsk_tasks (author_id, assignee_id) WHERE status = \'review\' AND deleted_at IS NULL');
            DB::statement('CREATE INDEX tsk_tasks_search_ix ON tsk_tasks USING GIN (search)');
            DB::statement('CREATE INDEX tsk_tasks_sprint_ix ON tsk_tasks (sprint_id) WHERE sprint_id IS NOT NULL');
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

        $this->drop('tsk_tasks');
    }
};
