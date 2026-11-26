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
        $table = $this->db()->getQueryGrammar()->wrapTable('users');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS users_department_id_ix ON {$table} (department_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('wsp_invitations');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS wsp_invitations_invited_by_ix ON {$table} (invited_by)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS wsp_invitations_workspace_id_ix ON {$table} (workspace_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('wsp_workspaces');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS wsp_workspaces_parent_id_ix ON {$table} (parent_id)");

        if ($this->db()->getDriverName() === 'pgsql') {
            $table = $this->db()->getQueryGrammar()->wrapTable('wsp_invitations');
            $this->db()->statement("COMMENT ON COLUMN {$table}.role_id IS 'Optional role reference owned by the access package. The application resolves stale references when accepting an invitation.'");
            $table = $this->db()->getQueryGrammar()->wrapTable('wsp_workspaces');
            $this->db()->statement("COMMENT ON COLUMN {$table}.path IS 'Hierarchy path for subtree predicates. The parent foreign key is stored in parent_id. Partner membership grants no visibility into its parent.'");
        }
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS users_department_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS wsp_invitations_invited_by_ix');
        $this->db()->statement('DROP INDEX IF EXISTS wsp_invitations_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS wsp_workspaces_parent_id_ix');
    }
};
