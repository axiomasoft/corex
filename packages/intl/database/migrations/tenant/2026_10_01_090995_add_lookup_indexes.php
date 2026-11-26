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
        $table = $this->db()->getQueryGrammar()->wrapTable('corex_rate_current');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS corex_rate_current_snapshot_id_ix ON {$table} (snapshot_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('corex_reference_current');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS corex_reference_current_snapshot_id_ix ON {$table} (snapshot_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('corex_reference_records');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS corex_reference_records_parent_ix ON {$table} (snapshot_id, parent_namespace, parent_source_id)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS corex_rate_current_snapshot_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS corex_reference_current_snapshot_id_ix');
        $this->db()->statement('DROP INDEX IF EXISTS corex_reference_records_parent_ix');
    }
};
