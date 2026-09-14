<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const LEGACY_TABLES = [
        'tsk_processes',
        'tsk_stages',
        'tsk_projects',
        'tsk_tasks',
        'tsk_task_participants',
        'tsk_project_participants',
    ];

    /** @var list<string> */
    private const CANONICAL_TABLES = [
        'tsk_timers',
        'tsk_time_logs',
        'tsk_task_watchers',
        'tsk_task_targets',
        'tsk_tasks',
        'tsk_sprints',
        'tsk_stages',
        'tsk_projects',
    ];

    public function up(): void
    {
        $this->refuseLegacyData();
        $this->dropLegacyTables();
        $this->createCanonicalTables();
    }

    public function down(): void
    {
        $this->refuseCanonicalData();

        foreach (self::CANONICAL_TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function refuseLegacyData(): void
    {
        foreach (self::LEGACY_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException(
                    "CoreX Tasks cannot align a non-empty legacy table [{$table}]. Migrate its data explicitly before enabling the package migration.",
                );
            }
        }
    }

    private function refuseCanonicalData(): void
    {
        foreach (self::CANONICAL_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException(
                    "CoreX Tasks cannot roll back a non-empty canonical table [{$table}]. Remove or migrate its data explicitly first.",
                );
            }
        }
    }

    private function dropLegacyTables(): void
    {
        foreach (['tsk_task_participants', 'tsk_project_participants', 'tsk_tasks', 'tsk_stages', 'tsk_projects', 'tsk_processes'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createCanonicalTables(): void
    {
        Schema::create('tsk_projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained('wsp_workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->jsonb('settings')->default('{}');
            $table->jsonb('created_by');
            $this->timestamps($table);
            $table->softDeletesTz();
        });

        Schema::create('tsk_stages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('tsk_projects')->cascadeOnDelete();
            $table->string('code', 64);
            $table->jsonb('name');
            $table->string('color', 16)->nullable();
            $table->integer('position')->default(0);
            $table->string('auto_status', 16)->nullable();
            $this->timestamps($table);
            $table->unique(['project_id', 'code']);
        });

        Schema::create('tsk_sprints', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('tsk_projects')->cascadeOnDelete();
            $table->string('name', 190);
            $table->text('goal')->nullable();
            $table->string('status', 16)->default('planned');
            $table->date('starts_on');
            $table->date('ends_on');
            $this->timestamps($table);
        });

        Schema::create('tsk_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained('wsp_workspaces')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('tsk_projects')->nullOnDelete();
            $table->foreignUuid('sprint_id')->nullable()->constrained('tsk_sprints')->nullOnDelete();
            $table->foreignUuid('stage_id')->nullable()->constrained('tsk_stages')->nullOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('priority', 8)->default('normal');
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('created_by');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->boolean('requires_acceptance')->default(false);
            $table->uuid('recurrence_rule_id')->nullable();
            $table->timestampTz('recurrence_occurs_at')->nullable();
            $table->integer('estimate_minutes')->nullable();
            $table->integer('spent_minutes')->default(0);
            $table->decimal('position', total: 20, places: 10)->default(0);

            if (DB::getDriverName() === 'pgsql') {
                $table->addColumn('tsvector', 'search')->nullable();
            } else {
                $table->text('search')->nullable();
            }

            $this->timestamps($table);
            $table->softDeletesTz();
        });

        Schema::table('tsk_tasks', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('tsk_tasks')->cascadeOnDelete();
        });

        Schema::create('tsk_task_targets', function (Blueprint $table): void {
            $table->foreignUuid('task_id')->constrained('tsk_tasks')->cascadeOnDelete();
            $table->string('entity_type', 64);
            $table->uuid('entity_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['task_id', 'entity_type', 'entity_id']);
            $table->index(['entity_type', 'entity_id'], 'tsk_tt_entity_ix');
        });

        Schema::create('tsk_task_watchers', function (Blueprint $table): void {
            $table->foreignUuid('task_id')->constrained('tsk_tasks')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['task_id', 'user_id']);
        });

        Schema::create('tsk_time_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tsk_tasks')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->integer('minutes');
            $table->date('spent_on');
            $table->text('comment')->nullable();
            $table->string('source', 8)->default('manual');
            $this->timestamps($table);
            $table->index('task_id', 'tsk_tl_task_ix');
            $table->index(['user_id', 'spent_on'], 'tsk_tl_user_ix');
        });

        Schema::create('tsk_timers', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('task_id')->constrained('tsk_tasks')->cascadeOnDelete();
            $table->timestampTz('started_at')->useCurrent();
            $this->timestamps($table);
        });

        $this->addDatabaseConstraintsAndIndexes();
    }

    private function timestamps(Blueprint $table): void
    {
        $table->timestampTz('created_at')->useCurrent();
        $table->timestampTz('updated_at')->useCurrent();
    }

    private function addDatabaseConstraintsAndIndexes(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            "ALTER TABLE tsk_projects ADD CONSTRAINT tsk_projects_status_chk CHECK (status IN ('active','archived'))",
            "ALTER TABLE tsk_stages ADD CONSTRAINT tsk_stages_auto_status_chk CHECK (auto_status IN ('in_progress','done'))",
            "COMMENT ON COLUMN tsk_stages.auto_status IS 'Перенос карточки в колонку опционально файрит FSM задачи (task.start / task.complete). NULL = чисто визуальная колонка'",
            "ALTER TABLE tsk_sprints ADD CONSTRAINT tsk_sprints_status_chk CHECK (status IN ('planned','active','completed'))",
            'ALTER TABLE tsk_sprints ADD CONSTRAINT tsk_sprints_dates_chk CHECK (ends_on >= starts_on)',
            "ALTER TABLE tsk_tasks ADD CONSTRAINT tsk_tasks_status_chk CHECK (status IN ('open','in_progress','waiting','review','done','cancelled'))",
            "ALTER TABLE tsk_tasks ADD CONSTRAINT tsk_tasks_priority_chk CHECK (priority IN ('low','normal','high','urgent'))",
            "ALTER TABLE tsk_tasks ADD CONSTRAINT tsk_tasks_completed_chk CHECK (status <> 'done' OR completed_at IS NOT NULL)",
            'ALTER TABLE tsk_tasks ADD CONSTRAINT tsk_tasks_recurrence_chk CHECK ((recurrence_rule_id IS NULL) = (recurrence_occurs_at IS NULL))',
            'ALTER TABLE tsk_time_logs ADD CONSTRAINT tsk_time_logs_minutes_chk CHECK (minutes > 0)',
            "ALTER TABLE tsk_time_logs ADD CONSTRAINT tsk_time_logs_source_chk CHECK (source IN ('manual','timer'))",
            'CREATE INDEX tsk_projects_ws_ix ON tsk_projects (workspace_id, status) WHERE deleted_at IS NULL',
            "CREATE UNIQUE INDEX tsk_sprints_active_uq ON tsk_sprints (project_id) WHERE status = 'active'",
            'CREATE INDEX tsk_tasks_assignee_ix ON tsk_tasks (assignee_id, status) WHERE deleted_at IS NULL',
            'CREATE INDEX tsk_tasks_kanban_ix ON tsk_tasks (project_id, stage_id, position) WHERE deleted_at IS NULL',
            "CREATE INDEX tsk_tasks_due_ix ON tsk_tasks (due_at) WHERE status IN ('open','in_progress','waiting','review') AND deleted_at IS NULL",
            'CREATE INDEX tsk_tasks_sprint_ix ON tsk_tasks (sprint_id) WHERE sprint_id IS NOT NULL',
            'CREATE INDEX tsk_tasks_search_ix ON tsk_tasks USING GIN (search)',
            "CREATE INDEX tsk_tasks_review_ix ON tsk_tasks (author_id, assignee_id) WHERE status = 'review' AND deleted_at IS NULL",
        ] as $statement) {
            DB::statement($statement);
        }
    }
};
