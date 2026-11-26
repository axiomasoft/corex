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
        $table = $this->db()->getQueryGrammar()->wrapTable('med_conversions');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS med_conversions_file_ix ON {$table} (file_id)");

        $table = $this->db()->getQueryGrammar()->wrapTable('med_operations');
        $this->db()->statement("CREATE INDEX IF NOT EXISTS med_operations_file_kind_ix ON {$table} (file_id, kind)");
    }

    public function down(): void
    {
        $this->db()->statement('DROP INDEX IF EXISTS med_conversions_file_ix');
        $this->db()->statement('DROP INDEX IF EXISTS med_operations_file_kind_ix');
    }
};
