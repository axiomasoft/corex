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
        $table = $this->db()->getQueryGrammar()->wrapTable('corex_transfer_outbox');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS corex_transfer_outbox_job_id_ix ON {$table} (job_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('corex_transfer_refs');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS corex_transfer_refs_job_id_ix ON {$table} (job_id)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS corex_transfer_outbox_job_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS corex_transfer_refs_job_id_ix');
    }
};
