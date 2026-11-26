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
        $table = $this->db()->getQueryGrammar()->wrapTable('corex_task_series_state');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS corex_task_series_state_series_key_active_revision_ix ON {$table} (series_key, active_revision)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS corex_task_series_state_series_key_active_revision_ix');
    }
};
