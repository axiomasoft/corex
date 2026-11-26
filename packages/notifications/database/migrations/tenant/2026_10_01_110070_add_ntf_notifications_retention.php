<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->db()->getSchemaBuilder()->table('ntf_notifications', function (Blueprint $table): void {
            $table->timestampTz('pruned_at')->nullable();
        });
        $table = $this->db()->getQueryGrammar()->wrapTable('ntf_notifications');
        $this->db()->statement("CREATE INDEX ntf_notifications_retention_ix ON {$table} (account_id, account_incarnation, workspace_scope, read_at, id) WHERE read_at IS NOT NULL AND pruned_at IS NULL");
        $this->db()->statement('DROP INDEX IF EXISTS ntf_notifications_inbox_ix');
        $this->db()->statement("CREATE INDEX ntf_notifications_inbox_ix ON {$table} (account_id, account_incarnation, workspace_scope, recipient_kind, recipient_id, id) WHERE pruned_at IS NULL");
    }

    public function down(): void
    {
        if ($this->db()->table('ntf_notifications')->whereNotNull('pruned_at')->exists()) {
            throw new RuntimeException('MigrationRollbackDenied: archived notifications require their replay records.');
        }
        $this->db()->getSchemaBuilder()->table('ntf_notifications', function (Blueprint $table): void {
            $table->dropIndex('ntf_notifications_retention_ix');
            $table->dropIndex('ntf_notifications_inbox_ix');
            $table->dropColumn('pruned_at');
            $table->index(['account_id', 'account_incarnation', 'workspace_scope', 'recipient_kind', 'recipient_id', 'id'], 'ntf_notifications_inbox_ix');
        });
    }
};
