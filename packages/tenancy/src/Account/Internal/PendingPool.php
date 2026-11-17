<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ProvisionParams;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Provisioning\Contracts\AccountResources;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;
use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use stdClass;

/**
 * Pending-pool mechanics (B-11 §5.2, AC-4/AC-5): keeps `pending_pool_size`
 * provisioned account resources per cell so signup can claim a prepared resource.
 * Pending accounts carry
 * `status='pending'`, `slug`/`owner_identity_id` NULL — the frozen
 * DB_SCHEMA table-CHECK on `root_accounts` already allows that state
 * (D14/P2.2), so no `pending_since` column is needed: `status` alone encodes "not yet claimed".
 *
 * Claiming (premortem A6): {@see pullPending()} flips `status`
 * `pending → provisioning` inside a `SELECT … FOR UPDATE SKIP LOCKED`
 * transaction — two concurrent callers never claim the same row. `slug`/
 * `owner_identity_id` stay NULL: the full pending→active transition (slug,
 * owner, status=trial) is P2.12's `AccountLifecycle::activateFromPool`
 * (Scope Excluded) — this class only reserves the row and lays the
 * atomicity invariant for that later transition.
 */
final readonly class PendingPool
{
    public function __construct(
        private string $centralConnection,
        private DatabaseTenantProvisioner $provisioner,
        private TemplateIntegrityChecks $templateIntegrityVerifier,
        private ConnectionResolverInterface $connections,
        private AccountResources $resources,
    ) {}

    /**
     * Create $count pending болванки on $clusterId (least-loaded active
     * cluster per call if omitted), cloning each database synchronously
     * from the cell's current ready template (D24 parity gate).
     *
     * @return list<string> created account ids
     */
    public function createPending(int $count, ?string $clusterId = null): array
    {
        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $created[] = $this->provisionOne(clusterId: $clusterId, status: 'pending');
        }

        return $created;
    }

    /**
     * Number of pending (unclaimed) болванки, optionally scoped to one
     * cluster — used by the pending-create command to top up to
     * `root_clusters.pending_pool_size`.
     */
    public function pendingCount(?string $clusterId = null): int
    {
        $query = $this->db()
            ->table('root_accounts')
            ->where('status', 'pending');

        if ($clusterId !== null) {
            $query->where('cluster_id', $clusterId);
        }

        return $query->count();
    }

    /**
     * Claim a pending account from the least-loaded cell (`ORDER BY
     * db_count`); an empty pool falls back to a synchronous clone
     * (< 10с, AC-5).
     */
    public function pullPending(): Account
    {
        $claimedId = $this->db()->transaction(function (): ?string {
            $candidate = $this->db()
                ->table('root_accounts as ra')
                ->join('root_clusters as rc', 'rc.id', '=', 'ra.cluster_id')
                ->where('ra.status', 'pending')
                ->orderBy('rc.db_count')
                ->select('ra.id')
                ->lock('for update of ra skip locked')
                ->first();

            if ($candidate === null) {
                return null;
            }

            $account = Account::on($this->centralConnection)->findOrFail($candidate->id);

            if (! $account instanceof Account) {
                throw new LogicException('Expected a pending account.');
            }
            $this->templateIntegrityVerifier->verifyAccount(new TemplateAccountView(templateVersion: $account->template_version, clusterId: $account->cluster_id, databaseName: $account->db_name));

            $this->db()
                ->table('root_accounts')
                ->where('id', $candidate->id)
                ->update(['status' => 'provisioning']);

            return DatabaseValue::text($candidate->id);
        });

        $id = $claimedId ?? $this->provisionOne(clusterId: null, status: 'provisioning');

        return Account::on($this->centralConnection)->findOrFail($id);
    }

    /**
     * Discard stale pending resources through the owner, then delete root_accounts
     * — either by `template_version` (schema drifted from a new golden
     * template) and/or by age (`created_at` older than $olderThan). At
     * least one filter is required: an unfiltered clear would nuke the
     * whole pool.
     *
     * @return int number of болванки cleared
     */
    public function clearPending(?int $templateVersion = null, ?DateTimeInterface $olderThan = null): int
    {
        if ($templateVersion === null && $olderThan === null) {
            throw new RuntimeException('pending-clear requires --template-version and/or --older-than.');
        }

        if ($this->db()->transactionLevel() !== 0) {
            throw new RuntimeException('Pending cleanup must run outside a transaction.');
        }

        $query = $this->db()
            ->table('root_accounts')
            ->where(fn (Builder $query) => $query->where('status', 'pending')
                ->orWhere(fn (Builder $query) => $query->where('status', 'provisioning')->where('pool_cleanup', true)));

        if ($templateVersion !== null) {
            $query->where('template_version', $templateVersion);
        }

        if ($olderThan !== null) {
            $query->where('created_at', '<', $olderThan);
        }

        $stale = $query->get(['id', 'db_name', 'cluster_id']);
        $cleared = 0;

        foreach ($stale as $row) {
            // A committed claim removes the row from signup selection before external DDL.
            // The marker makes a failed DROP recoverable by the next cleanup run.
            $claimed = $this->db()->table('root_accounts')
                ->where('id', $row->id)
                ->where(fn (Builder $query) => $query->where('status', 'pending')
                    ->orWhere(fn (Builder $query) => $query->where('status', 'provisioning')->where('pool_cleanup', true)))
                ->update(['status' => 'provisioning', 'pool_cleanup' => true]);

            if ($claimed !== 1) {
                continue;
            }

            $this->resources->discardUnclaimed(new AccountRef(
                id: DatabaseValue::text($row->id), slug: '', status: 'provisioning', features: [], limits: [],
            ));

            $this->db()->transaction(function () use ($row, &$cleared): void {
                $deleted = $this->db()
                    ->table('root_accounts')
                    ->where('id', $row->id)
                    ->where('status', 'provisioning')
                    ->where('pool_cleanup', true)
                    ->delete();

                if ($deleted === 0) {
                    return;
                }

                $this->db()
                    ->table('root_clusters')
                    ->where('id', $row->cluster_id)
                    ->decrement('db_count');
                $cleared++;
            });
        }

        return $cleared;
    }

    private function provisionOne(?string $clusterId, string $status): string
    {
        $cluster = $clusterId !== null
            ? $this->clusterRow($clusterId)
            : $this->leastLoadedActiveCluster();

        $template = $this->readyTemplateFor(DatabaseValue::integer($cluster->template_version));

        $this->templateIntegrityVerifier->verifyCurrent(
            version: DatabaseValue::integer($template->version),
            clusterId: DatabaseValue::text($cluster->id),
        );

        $id = (string) Str::uuid7();
        $dbName = 'acc_'.str_replace('-', '', $id);

        Account::on($this->centralConnection)->create([
            'id' => $id,
            'cluster_id' => $cluster->id,
            'db_name' => $dbName,
            'status' => 'provisioning',
            'template_version' => $template->version,
            'embedding_model' => $template->embedding_model,
            'embedding_dim' => $template->embedding_dim,
        ]);

        $this->provisioner->provision(new ProvisionParams(
            accountId: $id,
            clusterId: DatabaseValue::text($cluster->id),
            templateVersion: DatabaseValue::integer($template->version),
            embeddingModel: DatabaseValue::text($template->embedding_model),
            embeddingDim: DatabaseValue::integer($template->embedding_dim),
        ));

        $this->db()->transaction(function () use ($cluster, $id, $status): void {
            $this->db()->table('root_accounts')
                ->where('id', $id)->update(['status' => $status]);

            $this->db()
                ->table('root_clusters')
                ->where('id', $cluster->id)
                ->increment('db_count');
        });

        return $id;
    }

    private function clusterRow(string $clusterId): stdClass
    {
        $cluster = $this->db()
            ->table('root_clusters')
            ->where('id', $clusterId)
            ->first();

        if ($cluster === null) {
            throw new RuntimeException("Cluster [{$clusterId}] not found in root_clusters.");
        }

        return $cluster;
    }

    private function leastLoadedActiveCluster(): stdClass
    {
        $cluster = $this->db()
            ->table('root_clusters')
            ->where('status', 'active')
            ->orderBy('db_count')
            ->first();

        if ($cluster === null) {
            throw new RuntimeException('No active cluster available in root_clusters.');
        }

        return $cluster;
    }

    private function readyTemplateFor(int $version): stdClass
    {
        $template = $this->db()
            ->table('root_templates')
            ->where('version', $version)
            ->first();

        if ($template === null || $template->status !== 'ready') {
            throw new RuntimeException("Template version [{$version}] is not ready for cloning (template-parity gate, D24).");
        }

        return $template;
    }

    private function db(): Connection
    {
        /** @var Connection */
        $connection = $this->connections->connection($this->centralConnection);

        return $connection;
    }
}
