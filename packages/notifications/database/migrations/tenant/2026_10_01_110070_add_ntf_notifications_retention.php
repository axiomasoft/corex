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
        $connection = $this->db();
        $connection->transaction(static function () use ($connection): void {
            $table = $connection->getQueryGrammar()->wrapTable('ntf_notifications');

            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement("LOCK TABLE {$table} IN ACCESS EXCLUSIVE MODE");
            } else {
                // Reserve the SQLite writer before checking for archived rows.
                $connection->statement("UPDATE {$table} SET id = id WHERE 0 = 1");
            }

            $schema = $connection->getSchemaBuilder();
            $hasArchiveMarker = $schema->hasColumn('ntf_notifications', 'pruned_at');
            if ($hasArchiveMarker && $connection->table('ntf_notifications')->whereNotNull('pruned_at')->exists()) {
                throw new RuntimeException('MigrationRollbackDenied: archived notifications require their replay records.');
            }

            $connection->statement('DROP INDEX IF EXISTS ntf_notifications_retention_ix');
            $connection->statement('DROP INDEX IF EXISTS ntf_notifications_inbox_ix');
            if ($hasArchiveMarker) {
                $schema->table('ntf_notifications', static function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('pruned_at');
                });
            }
            $connection->statement("CREATE INDEX ntf_notifications_inbox_ix ON {$table} (account_id, account_incarnation, workspace_scope, recipient_kind, recipient_id, id)");
        });
    }
};
