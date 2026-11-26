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
        $connection = $this->db();
        // SQLite's migration journal can lag behind committed DDL. A retry also repairs an
        // installation that stopped partway through the original, nontransactional migration.
        $connection->transaction(static function () use ($connection): void {
            $schema = $connection->getSchemaBuilder();

            if (! $schema->hasColumn('ntf_notifications', 'pruned_at')) {
                $schema->table('ntf_notifications', static function (Blueprint $table): void {
                    $table->timestampTz('pruned_at')->nullable();
                });
            }

            $table = $connection->getQueryGrammar()->wrapTable('ntf_notifications');
            $connection->statement("CREATE INDEX IF NOT EXISTS ntf_notifications_retention_ix ON {$table} (account_id, account_incarnation, workspace_scope, read_at, id) WHERE read_at IS NOT NULL AND pruned_at IS NULL");
            // The old inbox index has the same name and covers archived rows as well.
            $connection->statement('DROP INDEX IF EXISTS ntf_notifications_inbox_ix');
            $connection->statement("CREATE INDEX ntf_notifications_inbox_ix ON {$table} (account_id, account_incarnation, workspace_scope, recipient_kind, recipient_id, id) WHERE pruned_at IS NULL");
        });
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
