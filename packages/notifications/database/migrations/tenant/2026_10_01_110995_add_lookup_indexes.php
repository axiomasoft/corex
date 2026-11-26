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
        $table = $this->db()->getQueryGrammar()->wrapTable('ntf_digest_items');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS ntf_digest_items_run_id_ix ON {$table} (run_id)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS ntf_digest_items_run_id_ix');
    }
};
