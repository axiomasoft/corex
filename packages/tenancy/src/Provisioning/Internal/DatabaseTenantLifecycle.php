<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Internal;

use CoreX\Context\Contracts\TenantDatabaseLifecycle;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Events\AccountPurged;
use CoreX\Tenancy\Account\Events\ExportReady;
use CoreX\Tenancy\Provisioning\Contracts\AccountResources;
use CoreX\Tenancy\Provisioning\Data\AccountArchive;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;

/** CoreX account export metadata; physical effects and archive integrity belong to AccountResources. */
#[Internal]
final readonly class DatabaseTenantLifecycle implements TenantDatabaseLifecycle
{
    public function __construct(
        private string $centralConnection,
        private ConnectionResolverInterface $connections,
        private ClockInterface $clock,
        private Dispatcher $events,
        private AccountResources $resources,
    ) {}

    public function export(AccountRef $account, string $kind): string
    {
        $exportId = (string) Str::uuid7();

        $this->db()->table('root_account_exports')->insert([
            'id' => $exportId,
            'account_id' => $account->id,
            'kind' => $kind,
            'status' => 'pending',
            'formats' => json_encode([], JSON_THROW_ON_ERROR),
            'purge_after' => $this->clock->now()->modify('+30 days'),
            'url_expires_at' => $this->clock->now()->modify('+14 days'),
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);

        $this->db()->table('root_account_exports')
            ->where('id', $exportId)
            ->update(['status' => 'running', 'updated_at' => $this->clock->now()]);

        $archive = $this->resources->export($account, $exportId, $kind);
        $this->verifyArchive($account, $archive);

        $this->db()->table('root_account_exports')
            ->where('id', $exportId)
            ->update([
                'status' => 'ready',
                'formats' => json_encode($archive->formats, JSON_THROW_ON_ERROR),
                'storage_path' => $archive->reference,
                'size_bytes' => $archive->sizeBytes,
                'updated_at' => $this->clock->now(),
            ]);

        $this->events->dispatch(new ExportReady(account: $account, exportId: $exportId));

        return $exportId;
    }

    public function drop(AccountRef $account): void
    {
        $latestExport = $this->db()->table('root_account_exports')
            ->where('account_id', $account->id)->latest()
            ->orderByDesc('id')
            ->first();

        if ($latestExport === null || $latestExport->status !== 'ready') {
            throw new RuntimeException(
                "Refusing to drop account [{$account->id}]'s database: no ready export on record (export-before-DROP invariant, D13/AC-7).",
            );
        }

        $archive = new AccountArchive(DatabaseValue::text($latestExport->storage_path), DatabaseValue::integer($latestExport->size_bytes));
        $this->verifyArchive($account, $archive);
        $this->resources->drop($account, $archive);

        $this->events->dispatch(new AccountPurged($account));
    }

    private function verifyArchive(AccountRef $account, AccountArchive $archive): void
    {
        if (! $this->resources->verifyArchive($account, $archive)) {
            throw new RuntimeException('The physical owner did not verify this account archive; refusing ready or deletion.');
        }
    }

    private function db(): Connection
    {
        /** @var Connection */
        $connection = $this->connections->connection($this->centralConnection);

        return $connection;
    }
}
