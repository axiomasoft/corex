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
        $table = $this->db()->getQueryGrammar()->wrapTable('crm_activities');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_activities_workspace_id_ix ON {$table} (workspace_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_activities_owner_id_ix ON {$table} (owner_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('crm_companies');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_companies_source_id_ix ON {$table} (source_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_companies_owner_id_ix ON {$table} (owner_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_companies_workspace_id_ix ON {$table} (workspace_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('crm_contacts');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_contacts_source_id_ix ON {$table} (source_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_contacts_owner_id_ix ON {$table} (owner_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_contacts_workspace_id_ix ON {$table} (workspace_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('crm_deals');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_deals_lost_reason_id_ix ON {$table} (lost_reason_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_deals_source_id_ix ON {$table} (source_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_deals_stage_id_ix ON {$table} (stage_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_deals_company_id_ix ON {$table} (company_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_deals_owner_id_ix ON {$table} (owner_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_deals_pipeline_id_ix ON {$table} (pipeline_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_deals_workspace_id_ix ON {$table} (workspace_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('crm_leads');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_converted_company_id_ix ON {$table} (converted_company_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_converted_contact_id_ix ON {$table} (converted_contact_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_converted_deal_id_ix ON {$table} (converted_deal_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_lost_reason_id_ix ON {$table} (lost_reason_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_source_id_ix ON {$table} (source_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_stage_id_ix ON {$table} (stage_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_workspace_id_ix ON {$table} (workspace_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_owner_id_ix ON {$table} (owner_id)");
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_leads_pipeline_id_ix ON {$table} (pipeline_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('crm_pipelines');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS crm_pipelines_workspace_id_ix ON {$table} (workspace_id)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS crm_activities_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_activities_owner_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_companies_source_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_companies_owner_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_companies_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_contacts_source_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_contacts_owner_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_contacts_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_deals_lost_reason_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_deals_source_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_deals_stage_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_deals_company_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_deals_owner_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_deals_pipeline_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_deals_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_converted_company_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_converted_contact_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_converted_deal_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_lost_reason_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_source_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_stage_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_workspace_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_owner_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_leads_pipeline_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS crm_pipelines_workspace_id_ix');
    }
};
