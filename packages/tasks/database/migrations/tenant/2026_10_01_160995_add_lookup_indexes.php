<?php

declare(strict_types=1);

use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $table = $this->db()->getQueryGrammar()->wrapTable('tsk_projects');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_projects_owner_id_ix ON {$table} (owner_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_projects_workspace_id_ix ON {$table} (workspace_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('tsk_sprints');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_sprints_project_id_ix ON {$table} (project_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('tsk_task_watchers');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_task_watchers_user_id_ix ON {$table} (user_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('tsk_tasks');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_tasks_parent_id_ix ON {$table} (parent_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_tasks_stage_id_ix ON {$table} (stage_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_tasks_workspace_id_ix ON {$table} (workspace_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_tasks_assignee_id_ix ON {$table} (assignee_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_tasks_author_id_ix ON {$table} (author_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_tasks_project_id_ix ON {$table} (project_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('tsk_timers');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS tsk_timers_task_id_ix ON {$table} (task_id)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS tsk_projects_owner_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_projects_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_sprints_project_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_task_watchers_user_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_tasks_parent_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_tasks_stage_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_tasks_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_tasks_assignee_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_tasks_author_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_tasks_project_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS tsk_timers_task_id_ix');
    }
};
