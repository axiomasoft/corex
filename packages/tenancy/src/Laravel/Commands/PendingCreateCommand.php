<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Commands;

use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Internal\DatabaseValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Packman\Attributes\Internal;

/**
 * Keeps `root_clusters.pending_pool_size` pre-cloned account databases
 * ready per cell (AC-4, B-11 §5.2). Without `--count`, tops each targeted
 * cluster up to its own `pending_pool_size` (steady-state/cron usage);
 * `--count=N` overrides with an explicit number (ad hoc seeding).
 */
#[Internal]
final class PendingCreateCommand extends Command
{
    protected $signature = 'corex:tenancy:pending-create {--count=} {--cluster=}';

    protected $description = 'Top up (or explicitly seed) the pending account pool for one or all active clusters.';

    public function handle(PendingPool $pool, ?TenancyOptions $options = null): int
    {
        $centralConnection = ($options ?? $this->laravel->make(TenancyOptions::class))->centralConnection;
        $explicitCount = $this->option('count') !== null ? (int) $this->option('count') : null;
        $clusterId = $this->option('cluster');

        $clusters = $clusterId !== null
            ? [$clusterId]
            : DB::connection($centralConnection)
                ->table('root_clusters')
                ->where('status', 'active')
                ->pluck('id')
                ->all();

        foreach ($clusters as $id) {
            $id = DatabaseValue::text($id);
            $count = $explicitCount ?? $this->topUpCount(centralConnection: $centralConnection, pool: $pool, clusterId: $id);

            if ($count <= 0) {
                continue;
            }

            $created = $pool->createPending(count: $count, clusterId: $id);

            $this->components->info(sprintf('Cluster [%s]: created %d pending account(s).', $id, count($created)));
        }

        return self::SUCCESS;
    }

    private function topUpCount(string $centralConnection, PendingPool $pool, string $clusterId): int
    {
        $poolSize = DatabaseValue::integer(DB::connection($centralConnection)
            ->table('root_clusters')
            ->where('id', $clusterId)
            ->value('pending_pool_size') ?? 0);

        return max(0, $poolSize - $pool->pendingCount($clusterId));
    }
}
