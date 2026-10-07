<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Commands;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Identity\Contracts\IdentitySyncer;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Internal\DatabaseValue;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Control-plane sweep (B-11 §3.2): re-syncs every `root_identity_accounts`
 * row that is `failed`/`pending`, or whose `synced_at` has fallen behind its
 * identity's `updated_at` — the retry path for {@see IdentitySyncer}'s
 * per-account failure isolation (AC-16 з).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IdentitiesReconcileCommand extends Command
{
    protected $signature = 'corex:tenancy:reconcile-identities';

    protected $description = 'Re-sync failed, pending, and stale identity projections into their account users tables.';

    public function handle(IdentitySyncer $syncer, ?TenancyOptions $options = null): int
    {
        $centralConnection = ($options ?? $this->laravel->make(TenancyOptions::class))->centralConnection;

        $rows = DB::connection($centralConnection)
            ->table('root_identity_accounts as ria')
            ->join('root_identities as ri', 'ri.id', '=', 'ria.identity_id')
            ->where(function (Builder $query): void {
                $query->whereIn('ria.sync_status', ['failed', 'pending'])
                    ->orWhereColumn('ria.synced_at', '<', 'ri.updated_at');
            })
            ->select('ria.identity_id', 'ria.account_id')
            ->get();

        foreach ($rows as $row) {
            $syncer->syncToAccount(DatabaseValue::text($row->identity_id), DatabaseValue::text($row->account_id));
        }

        $this->components->info(sprintf('Reconciled %d identity/account pair(s).', $rows->count()));

        return self::SUCCESS;
    }
}
