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
        $connection = $this->db();
        $connection->transaction(static function () use ($connection): void {
            $table = $connection->getQueryGrammar()->wrapTable('sys_wakeup_requests');
            $connection->statement(<<<SQL
                CREATE INDEX IF NOT EXISTS sys_wakeup_requests_runnable_due_ix ON {$table} (account_id,
                    (case when status in ('running', 'cancel-requested') then lease_until when status = 'retry-scheduled' then retry_at else fire_at end), id)
                WHERE status IN ('pending', 'retry-scheduled', 'running', 'cancel-requested')
                SQL);
            $connection->statement('DROP INDEX IF EXISTS sys_wakeup_requests_lease_ix');
            $connection->statement('DROP INDEX IF EXISTS sys_wakeup_requests_due_ix');
        });
    }

    public function down(): void
    {
        $connection = $this->db();
        $connection->transaction(static function () use ($connection): void {
            $table = $connection->getQueryGrammar()->wrapTable('sys_wakeup_requests');
            $connection->statement("CREATE INDEX IF NOT EXISTS sys_wakeup_requests_due_ix ON {$table} (account_id, status, fire_at, id)");
            $connection->statement("CREATE INDEX IF NOT EXISTS sys_wakeup_requests_lease_ix ON {$table} (account_id, status, lease_until)");
            $connection->statement('DROP INDEX IF EXISTS sys_wakeup_requests_runnable_due_ix');
        });
    }
};
