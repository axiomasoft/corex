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
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS aut_role_assignments_granted_by_ix');
        $this->db()->statement('DROP INDEX IF EXISTS aut_role_assignments_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS aut_roles_cloned_from_id_ix');
    }
};
