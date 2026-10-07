<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Internal;

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use RuntimeException;

/**
 * Builds the tenant connection config from `root_clusters`+`db_name` (AC-9):
 * every call re-reads both tables, so a `cluster_id` cell-migration on
 * `root_accounts` changes the resolved connection without a deploy.
 */
final class ClusterAccountConnectionResolver implements AccountConnectionResolver
{
    public function __construct(
        private readonly string $centralConnection,
        private readonly ConnectionResolverInterface $connections,
    ) {}

    public function resolve(AccountRef $account): DatabaseConnectionConfig
    {
        $row = $this->db()
            ->table('root_accounts')
            ->where('id', $account->id)
            ->select(['db_name', 'cluster_id'])
            ->first();

        if ($row === null) {
            throw new RuntimeException("Account [{$account->id}] not found in root_accounts.");
        }

        $clusterId = DatabaseValue::text($row->cluster_id);
        $cluster = $this->db()
            ->table('root_clusters')
            ->where('id', $clusterId)
            ->select(['pgbouncer_host', 'pgbouncer_port', 'app_db_user'])
            ->first();

        if ($cluster === null) {
            throw new RuntimeException("Cluster [{$clusterId}] not found in root_clusters.");
        }

        return new DatabaseConnectionConfig(
            driver: 'pgsql',
            host: DatabaseValue::text($cluster->pgbouncer_host),
            port: DatabaseValue::integer($cluster->pgbouncer_port),
            database: DatabaseValue::text($row->db_name),
            username: DatabaseValue::text($cluster->app_db_user),
        );
    }

    private function db(): Connection
    {
        /** @var Connection */
        $connection = $this->connections->connection($this->centralConnection);

        return $connection;
    }
}
