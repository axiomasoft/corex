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
        $table = $this->db()->getQueryGrammar()->wrapTable('aut_role_assignments');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS aut_role_assignments_granted_by_ix ON {$table} (granted_by)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS aut_role_assignments_workspace_id_ix ON {$table} (workspace_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('aut_roles');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS aut_roles_cloned_from_id_ix ON {$table} (cloned_from_id)");

        if ($this->db()->getDriverName() === 'pgsql') {
            $table = $this->db()->getQueryGrammar()->wrapTable('aut_role_assignments');
            $this->db()->statement("COMMENT ON TABLE {$table} IS 'Role assignments apply to users, agents and API keys. An agent acting for a user is limited to permissions shared by the user and the agent role.'");
            $table = $this->db()->getQueryGrammar()->wrapTable('aut_role_matrix');
            $this->db()->statement("COMMENT ON TABLE {$table} IS 'Base grants use the widest scope across roles. A matching contextual grant overrides the base grant and may narrow it. A missing grant has no permissions.'");
        }
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS aut_role_assignments_granted_by_ix');
        $this->db()->statement('DROP INDEX IF EXISTS aut_role_assignments_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS aut_roles_cloned_from_id_ix');
    }
};
