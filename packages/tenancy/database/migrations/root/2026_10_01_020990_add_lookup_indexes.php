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
        $table = $this->db()->getQueryGrammar()->wrapTable('root_account_exports');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_account_exports_account_id_ix ON {$table} (account_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('root_account_schema_state');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_account_schema_state_batch_id_ix ON {$table} (batch_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('root_accounts');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_accounts_owner_identity_id_ix ON {$table} (owner_identity_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_accounts_cluster_id_ix ON {$table} (cluster_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('root_domains');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_domains_account_id_ix ON {$table} (account_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('root_idp_sessions');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_idp_sessions_identity_id_ix ON {$table} (identity_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('root_impersonation_grants');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_impersonation_grants_approved_by_ix ON {$table} (approved_by)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_impersonation_grants_staff_identity_id_ix ON {$table} (staff_identity_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('root_oauth_refresh_tokens');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS root_oauth_refresh_tokens_session_ix ON {$table} (session_id)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS root_account_exports_account_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS root_account_schema_state_batch_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS root_accounts_owner_identity_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS root_accounts_cluster_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS root_domains_account_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS root_idp_sessions_identity_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS root_impersonation_grants_approved_by_ix');
        $this->db()->statement('DROP INDEX IF EXISTS root_impersonation_grants_staff_identity_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS root_oauth_refresh_tokens_session_ix');
    }
};
