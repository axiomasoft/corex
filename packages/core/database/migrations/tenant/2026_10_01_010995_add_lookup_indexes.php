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
        $table = $this->db()->getQueryGrammar()->wrapTable('sys_department_user');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS sys_department_user_user_id_ix ON {$table} (user_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('sys_departments');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS sys_departments_parent_id_ix ON {$table} (parent_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS sys_departments_head_user_id_ix ON {$table} (head_user_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS sys_departments_workspace_id_ix ON {$table} (workspace_id)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS sys_department_user_user_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS sys_departments_parent_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS sys_departments_head_user_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS sys_departments_workspace_id_ix');
    }
};
