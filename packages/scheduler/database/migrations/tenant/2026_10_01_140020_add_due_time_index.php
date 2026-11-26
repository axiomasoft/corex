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
        $table = $this->db()->getQueryGrammar()->wrapTable('sys_wakeup_requests');
        $this->db()->statement('DROP INDEX IF EXISTS sys_wakeup_requests_lease_ix');
        $this->db()->statement('DROP INDEX IF EXISTS sys_wakeup_requests_due_ix');
        $this->db()->statement(<<<SQL
            CREATE INDEX sys_wakeup_requests_runnable_due_ix ON {$table} (account_id,
                (case when status in ('running', 'cancel-requested') then lease_until when status = 'retry-scheduled' then retry_at else fire_at end), id)
            WHERE status IN ('pending', 'retry-scheduled', 'running', 'cancel-requested')
            SQL);
    }

    public function down(): void
    {
        $table = $this->db()->getQueryGrammar()->wrapTable('sys_wakeup_requests');
        $this->db()->statement('DROP INDEX IF EXISTS sys_wakeup_requests_runnable_due_ix');
        $this->db()->statement("CREATE INDEX sys_wakeup_requests_due_ix ON {$table} (account_id, status, fire_at, id)");
        $this->db()->statement("CREATE INDEX sys_wakeup_requests_lease_ix ON {$table} (account_id, status, lease_until)");
    }
};
